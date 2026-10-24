<?php
/**
 * Essenza Furniture - Customer Migration Engine & Magic Link Onboarding
 *
 * Ingests Shopify customer CSV exports into WooCommerce without requiring
 * legacy passwords, setting up complete billing/shipping profiles and generating
 * tokenized 1-click onboarding links.
 *
 * Usage:
 *   Dry-Run: php migrate-shopify-customers.php --file=sample-shopify-customers.csv --dry-run
 *   Import:  php migrate-shopify-customers.php --file=sample-shopify-customers.csv --import
 *
 * @package Essenza_Fulfillment
 */

require_once __DIR__ . '/wp-load.php';

class Shopify_Customer_Migrator {

    private string $csv_path;
    private bool $is_dry_run = true;
    private array $customers = [];

    public function __construct( string $csv_path, bool $dry_run = true ) {
        $this->csv_path   = $csv_path;
        $this->is_dry_run = $dry_run;
    }

    public static function run( $argv ) {
        $file = __DIR__ . '/sample-shopify-customers.csv';
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
            die( "Error: Customer CSV not found at '{$file}'\n" );
        }

        $migrator = new self( $file, $dry_run );
        $migrator->execute();
        exit( 0 );
    }

    public function execute() {
        echo "=========================================================================\n";
        echo "ESSENZA FURNITURE - SHOPIFY CUSTOMER MIGRATION BRIDGE\n";
        echo "Mode: " . ( $this->is_dry_run ? "DRY-RUN AUDIT" : "LIVE CUSTOMER INGESTION" ) . "\n";
        echo "Source: {$this->csv_path}\n";
        echo "=========================================================================\n\n";

        $this->parse_csv();
        $this->process_customers();
    }

    private function parse_csv() {
        $handle = fopen( $this->csv_path, 'r' );
        if ( ! $handle ) die( "Cannot open customer CSV.\n" );

        $headers = fgetcsv( $handle );
        $headers[0] = preg_replace( '/[\x00-\x1F\x80-\xFF]/', '', $headers[0] );

        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            if ( count( $row ) === count( $headers ) ) {
                $this->customers[] = array_combine( $headers, $row );
            }
        }
        fclose( $handle );

        echo "Parsed " . count( $this->customers ) . " customer profiles from Shopify export.\n\n";
    }

    private function process_customers() {
        $created = 0;
        $existing = 0;

        echo str_repeat( '-', 80 ) . "\n";
        printf( "%-24s | %-30s | %-12s | %-8s\n", "Name", "Email Address", "Historical $", "Action" );
        echo str_repeat( '-', 80 ) . "\n";

        foreach ( $this->customers as $cust ) {
            $email = sanitize_email( trim( $cust['Email'] ?? '' ) );
            $first = sanitize_text_field( trim( $cust['First Name'] ?? '' ) );
            $last  = sanitize_text_field( trim( $cust['Last Name'] ?? '' ) );
            $phone = sanitize_text_field( trim( $cust['Phone'] ?? '' ) );
            $spent = floatval( $cust['Total Spent'] ?? 0 );
            $orders= intval( $cust['Total Orders'] ?? 0 );

            if ( empty( $email ) || ! is_email( $email ) ) {
                echo "Skipping invalid email: '{$email}'\n";
                continue;
            }

            $user_id = email_exists( $email );

            if ( $user_id ) {
                $status = "EXISTING (ID: {$user_id})";
                $existing++;
            } else {
                $status = $this->is_dry_run ? "WILL CREATE" : "CREATED";
                $created++;
            }

            printf( "%-24s | %-30s | $%-11.2f | %-8s\n",
                substr( "{$first} {$last}", 0, 24 ),
                substr( $email, 0, 30 ),
                $spent,
                $status
            );

            if ( ! $this->is_dry_run ) {
                if ( ! $user_id ) {
                    // Create customer with secure uncrackable random password
                    $random_pass = wp_generate_password( 64, true, true );
                    $username = sanitize_user( current( explode( '@', $email ) ) );
                    if ( username_exists( $username ) ) {
                        $username .= '_' . wp_rand( 100, 999 );
                    }

                    $user_id = wp_insert_user( [
                        'user_login'   => $username,
                        'user_pass'    => $random_pass,
                        'user_email'   => $email,
                        'first_name'   => $first,
                        'last_name'    => $last,
                        'display_name' => trim( "{$first} {$last}" ) ?: $username,
                        'role'         => 'customer',
                    ] );

                    if ( is_wp_error( $user_id ) ) {
                        echo "  [ERROR] " . $user_id->get_error_message() . "\n";
                        continue;
                    }
                }

                // Populate Australian Billing & Shipping Addresses
                $addr1   = sanitize_text_field( $cust['Default Address Address1'] ?? '' );
                $city    = sanitize_text_field( $cust['Default Address City'] ?? '' );
                $state   = sanitize_text_field( $cust['Default Address Province Code'] ?? '' );
                $postcode= sanitize_text_field( $cust['Default Address Zip'] ?? '' );
                $country = sanitize_text_field( $cust['Default Address Country Code'] ?? 'AU' );

                $meta_map = [
                    'billing_first_name'   => $first,
                    'billing_last_name'    => $last,
                    'billing_email'        => $email,
                    'billing_phone'        => $phone,
                    'billing_address_1'    => $addr1,
                    'billing_city'         => $city,
                    'billing_state'        => $state,
                    'billing_postcode'     => $postcode,
                    'billing_country'      => $country,

                    'shipping_first_name'  => $first,
                    'shipping_last_name'   => $last,
                    'shipping_address_1'   => $addr1,
                    'shipping_city'        => $city,
                    'shipping_state'       => $state,
                    'shipping_postcode'    => $postcode,
                    'shipping_country'     => $country,

                    '_shopify_total_spent' => $spent,
                    '_shopify_orders_count'=> $orders,
                    '_shopify_migrated_at' => current_time( 'mysql' ),
                ];

                foreach ( $meta_map as $key => $val ) {
                    update_user_meta( $user_id, $key, $val );
                }

                // Generate 1-Click Activation Token for Email Campaign
                $token = hash_hmac( 'sha256', "{$user_id}|{$email}|" . wp_salt( 'auth' ), wp_salt( 'secure_auth' ) );
                update_user_meta( $user_id, '_essenza_magic_token', $token );
                update_user_meta( $user_id, '_essenza_magic_token_exp', time() + ( 14 * DAY_IN_SECONDS ) );

                $magic_link = add_query_arg( [
                    'essenza_auth' => $token,
                    'uid'          => $user_id,
                ], wc_get_page_permalink( 'myaccount' ) );

                echo "  -> 1-Click Activation Link: {$magic_link}\n";
            }
        }

        echo str_repeat( '-', 80 ) . "\n";
        echo "Summary: Processed " . count( $this->customers ) . " accounts. {$created} imported, {$existing} existing.\n\n";
    }
}

Shopify_Customer_Migrator::run( $argv );
