<?php
/**
 * Essenza Furniture - Automated Shopify Data Pipeline & Pre-Import Validator
 *
 * Usage:
 *   Dry-Run Validation:  php shopify-import-mapper.php --file=sample-shopify-export.csv --dry-run
 *   Live Import:         php shopify-import-mapper.php --file=sample-shopify-export.csv --import
 *
 * @package Essenza_Fulfillment
 */

// Load WordPress environment if available
$wp_load = __DIR__ . '/wp-load.php';
if ( file_exists( $wp_load ) ) {
    require_once $wp_load;
} else {
    echo "Notice: Running in standalone CLI mode (WordPress functions will be simulated for dry-run).\n";
}

class Shopify_Import_Mapper {

    private string $csv_path;
    private bool $is_dry_run = true;
    private array $rows = [];
    private array $headers = [];
    private array $validation_results = [];

    public function __construct( string $csv_path, bool $dry_run = true ) {
        $this->csv_path   = $csv_path;
        $this->is_dry_run = $dry_run;
    }

    public static function run( $argv ) {
        $file = __DIR__ . '/sample-shopify-export.csv';
        $dry_run = true;

        foreach ( $argv as $arg ) {
            if ( str_starts_with( $arg, '--file=' ) ) {
                $file = substr( $arg, 7 );
            } elseif ( $arg === '--import' || $arg === '--execute' ) {
                $dry_run = false;
            } elseif ( $arg === '--dry-run' ) {
                $dry_run = true;
            }
        }

        if ( ! file_exists( $file ) ) {
            die( "Error: CSV file not found at '{$file}'\n" );
        }

        $pipeline = new self( $file, $dry_run );
        $pipeline->execute();
        exit( 0 );
    }

    public function execute() {
        echo "=========================================================================\n";
        echo "ESSENZA FURNITURE - SHOPIFY TO WOOCOMMERCE INGEST PIPELINE\n";
        echo "Mode: " . ( $this->is_dry_run ? "DRY-RUN VALIDATION (No Database Writes)" : "LIVE DATABASE IMPORT" ) . "\n";
        echo "Source: {$this->csv_path}\n";
        echo "=========================================================================\n\n";

        $this->parse_csv();
        $this->validate_rows();
        $this->render_validation_report();

        if ( ! $this->is_dry_run ) {
            $this->perform_import();
        } else {
            echo "\nDry-run complete. To execute live import into WooCommerce, re-run with:\n";
            echo "  php shopify-import-mapper.php --file=" . basename( $this->csv_path ) . " --import\n\n";
        }
    }

    private function parse_csv() {
        $handle = fopen( $this->csv_path, 'r' );
        if ( ! $handle ) {
            die( "Failed to open CSV file.\n" );
        }

        $this->headers = fgetcsv( $handle );
        if ( ! $this->headers ) {
            die( "CSV appears to be empty or malformed.\n" );
        }

        // Clean UTF-8 BOM if present
        $this->headers[0] = preg_replace( '/[\x00-\x1F\x80-\xFF]/', '', $this->headers[0] );

        $row_idx = 1;
        while ( ( $data = fgetcsv( $handle ) ) !== false ) {
            if ( count( $data ) === count( $this->headers ) ) {
                $this->rows[] = array_combine( $this->headers, $data );
            } else {
                echo "Warning: Skipped line {$row_idx} due to column mismatch.\n";
            }
            $row_idx++;
        }
        fclose( $handle );

        echo "Parsed " . count( $this->rows ) . " records from Shopify export.\n\n";
    }

    /**
     * Pre-Import Validation & Smart Metadata Inference
     */
    private function validate_rows() {
        foreach ( $this->rows as $index => $row ) {
            $issues = [];
            $inferred = [];

            $handle = trim( $row['Handle'] ?? '' );
            $title  = trim( $row['Title'] ?? '' );
            $price  = floatval( $row['Variant Price'] ?? 0 );
            $weight = floatval( $row['Variant Grams'] ?? 0 );
            $tags   = strtolower( $row['Tags'] ?? '' );
            $vendor = strtolower( $row['Vendor'] ?? '' );

            if ( empty( $handle ) ) {
                $issues[] = ['type' => 'CRITICAL', 'msg' => 'Missing product Handle (slug)'];
            }
            if ( empty( $title ) ) {
                $issues[] = ['type' => 'CRITICAL', 'msg' => 'Missing product Title'];
            }
            if ( $price <= 0 ) {
                $issues[] = ['type' => 'CRITICAL', 'msg' => 'Price is zero or invalid'];
            }

            // Freight & Dimensional validation
            if ( $weight <= 0 ) {
                $issues[] = ['type' => 'WARNING', 'msg' => 'Variant Grams is 0 (bulky freight table rate needs weight)'];
            }

            // Fulfillment Origin Resolution & Fallback Logic
            $origin = trim( $row['Metafield: custom.fulfillment_origin'] ?? '' );
            if ( empty( $origin ) ) {
                // Sourcing inference rules
                if ( str_contains( $tags, 'warehouse' ) || str_contains( $tags, 'in-stock' ) ) {
                    $origin = 'in_house';
                    $inferred[] = "Inferred origin 'in_house' from tags [warehouse/in-stock]";
                } elseif ( str_contains( $tags, 'local' ) || str_contains( $tags, 'artisan' ) || str_contains( $tags, 'made-to-order' ) ) {
                    $origin = 'local_supplier';
                    $inferred[] = "Inferred origin 'local_supplier' from tags [artisan/made-to-order]";
                } elseif ( str_contains( $tags, 'import' ) || str_contains( $tags, 'sea-freight' ) || str_contains( $tags, 'bespoke' ) ) {
                    $origin = 'overseas_import';
                    $inferred[] = "Inferred origin 'overseas_import' from tags [import/sea-freight]";
                } else {
                    $origin = 'in_house'; // safe fallback
                    $issues[] = ['type' => 'WARNING', 'msg' => "No origin metafield or tag found; defaulted to 'in_house'"];
                }
            }

            // Freight Tier & Volume Inference
            $cubic = floatval( $row['Metafield: custom.cubic_meters'] ?? 0 );
            if ( $cubic <= 0 ) {
                $issues[] = ['type' => 'NOTICE', 'msg' => 'Missing cubic meters volume (container packing metric)'];
            }

            $tier = trim( $row['Metafield: custom.freight_tier'] ?? '' );
            if ( empty( $tier ) ) {
                $tier = match( $origin ) {
                    'overseas_import' => 'white_glove',
                    'local_supplier'  => 'bulky_furniture',
                    default           => ( $weight > 25000 ) ? 'bulky_furniture' : 'standard_parcel',
                };
                $inferred[] = "Inferred freight tier '{$tier}'";
            }

            $min_days = intval( $row['Metafield: custom.lead_time_min'] ?? 0 );
            $max_days = intval( $row['Metafield: custom.lead_time_max'] ?? 0 );
            if ( $min_days <= 0 || $max_days <= 0 ) {
                [ $min_days, $max_days ] = match( $origin ) {
                    'overseas_import' => [ 56, 75 ],
                    'local_supplier'  => [ 14, 21 ],
                    default           => [ 1, 3 ],
                };
                $inferred[] = "Defaulted lead times to {$min_days}–{$max_days} days";
            }

            $lead_label = match( $origin ) {
                'overseas_import' => "Custom Pre-Order: Estimated {$min_days}–{$max_days} days (Sea Freight Container)",
                'local_supplier'  => "Made to Order: Dispatches in {$min_days}–{$max_days} days from Australian Workshop",
                default           => "In Stock: Dispatches in {$min_days}–{$max_days} business days from Sydney Warehouse",
            };

            $this->validation_results[] = [
                'index'       => $index + 1,
                'handle'      => $handle,
                'title'       => $title,
                'sku'         => $row['Variant SKU'] ?? 'N/A',
                'price'       => $price,
                'weight_kg'   => $weight / 1000,
                'origin'      => $origin,
                'tier'        => $tier,
                'cubic'       => $cubic,
                'min_days'    => $min_days,
                'max_days'    => $max_days,
                'lead_label'  => $lead_label,
                'issues'      => $issues,
                'inferred'    => $inferred,
                'row_data'    => $row,
            ];
        }
    }

    private function render_validation_report() {
        echo "PRE-IMPORT AUDIT & DATA VALIDATION REPORT\n";
        echo str_repeat( '-', 75 ) . "\n";
        printf( "%-4s | %-28s | %-14s | %-10s | %-8s\n", "#", "Product Title / Handle", "Origin", "Price", "Status" );
        echo str_repeat( '-', 75 ) . "\n";

        $critical_count = 0;
        $warning_count  = 0;

        foreach ( $this->validation_results as $item ) {
            $has_crit = false;
            $has_warn = false;

            foreach ( $item['issues'] as $iss ) {
                if ( $iss['type'] === 'CRITICAL' ) $has_crit = true;
                if ( $iss['type'] === 'WARNING' )  $has_warn = true;
            }

            if ( $has_crit ) {
                $status = "❌ FAIL";
                $critical_count++;
            } elseif ( $has_warn ) {
                $status = "⚠️ WARN";
                $warning_count++;
            } else {
                $status = "✅ PASS";
            }

            $display_title = substr( $item['title'] ?: $item['handle'], 0, 26 );
            printf( "%-4d | %-28s | %-14s | $%-9.2f | %-8s\n",
                $item['index'],
                $display_title,
                $item['origin'],
                $item['price'],
                $status
            );

            if ( ! empty( $item['issues'] ) || ! empty( $item['inferred'] ) ) {
                foreach ( $item['issues'] as $iss ) {
                    echo "     [{$iss['type']}] {$iss['msg']}\n";
                }
                foreach ( $item['inferred'] as $inf ) {
                    echo "     [AUTO-FIX] {$inf}\n";
                }
            }
        }

        echo str_repeat( '-', 75 ) . "\n";
        echo "Summary: " . count( $this->validation_results ) . " products scanned. ";
        echo "{$critical_count} Critical errors, {$warning_count} Warnings.\n";

        if ( $critical_count > 0 ) {
            echo "⛔ CRITICAL ERRORS DETECTED: Review CSV rows above before executing live import.\n";
        } else {
            echo "✨ VALIDATION READY: All products have resolved origins and dimensions.\n";
        }
    }

    /**
     * Live WooCommerce Product Injection Engine
     */
    private function perform_import() {
        if ( ! function_exists( 'wc_get_product' ) ) {
            die( "Error: WordPress / WooCommerce runtime environment not detected. Cannot run live import.\n" );
        }

        echo "\nExecuting Live WooCommerce Catalog Injection...\n";

        $imported = 0;
        $updated  = 0;

        foreach ( $this->validation_results as $item ) {
            // Check critical errors
            foreach ( $item['issues'] as $iss ) {
                if ( $iss['type'] === 'CRITICAL' ) {
                    echo "Skipping Item #{$item['index']} due to critical issue: {$iss['msg']}\n";
                    continue 2;
                }
            }

            $handle = $item['handle'];
            $existing_post = get_page_by_path( $handle, OBJECT, 'product' );

            if ( $existing_post ) {
                $product = wc_get_product( $existing_post->ID );
                $action_label = "Updated";
                $updated++;
            } else {
                $product = new WC_Product_Simple();
                $product->set_slug( $handle );
                $action_label = "Created";
                $imported++;
            }

            $raw = $item['row_data'];

            $product->set_name( $item['title'] );
            $product->set_status( 'publish' );
            $product->set_catalog_visibility( 'visible' );
            $product->set_regular_price( $item['price'] );
            if ( ! empty( $raw['Variant Compare At Price'] ) && floatval( $raw['Variant Compare At Price'] ) > $item['price'] ) {
                $product->set_sale_price( $item['price'] );
                $product->set_regular_price( floatval( $raw['Variant Compare At Price'] ) );
            }

            if ( ! empty( $raw['Body (HTML)'] ) ) {
                $product->set_description( wp_kses_post( $raw['Body (HTML)'] ) );
            }
            $product->set_short_description( $item['lead_label'] );

            if ( ! empty( $item['sku'] ) ) {
                $product->set_sku( $item['sku'] );
            }
            if ( $item['weight_kg'] > 0 ) {
                $product->set_weight( $item['weight_kg'] );
            }

            // Manage stock
            $qty = intval( $raw['Variant Inventory Qty'] ?? 0 );
            if ( $qty > 0 ) {
                $product->set_manage_stock( true );
                $product->set_stock_quantity( $qty );
            } else {
                $product->set_manage_stock( false );
            }

            $product->save();
            $pid = $product->get_id();

            // Save Essenza Sourcing & Freight Metadata
            update_post_meta( $pid, '_essenza_origin_type', $item['origin'] );
            update_post_meta( $pid, '_essenza_lead_time_min_days', $item['min_days'] );
            update_post_meta( $pid, '_essenza_lead_time_max_days', $item['max_days'] );
            update_post_meta( $pid, '_essenza_lead_time_label', $item['lead_label'] );
            update_post_meta( $pid, '_essenza_freight_tier', $item['tier'] );
            if ( $item['cubic'] > 0 ) {
                update_post_meta( $pid, '_essenza_cubic_meters', $item['cubic'] );
            }
            update_post_meta( $pid, '_shopify_handle', $handle );

            // Tags & Category
            if ( ! empty( $raw['Type'] ) ) {
                wp_set_object_terms( $pid, $raw['Type'], 'product_cat', true );
            }
            if ( ! empty( $raw['Tags'] ) ) {
                $tags_array = array_map( 'trim', explode( ',', $raw['Tags'] ) );
                wp_set_object_terms( $pid, $tags_array, 'product_tag', true );
            }

            echo "  [{$action_label}] Product ID {$pid}: '{$item['title']}' (Origin: {$item['origin']} | Tier: {$item['tier']})\n";
        }

        echo "\nDatabase Import Completed: {$imported} new created, {$updated} updated.\n";
    }
}

Shopify_Import_Mapper::run( $argv );
