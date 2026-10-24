<?php
/**
 * Essenza Furniture - Enterprise Dynamic Admin Dashboard Controller
 *
 * Provides a secure, full-featured product management suite allowing administrators
 * to create, edit, delete, publish/unpublish, and upload images for WooCommerce products dynamically.
 *
 * @package Essenza_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Essenza_Admin_Dashboard {

    const PAGE_SLUG = 'admin-dashboard';

    /**
     * Initialize hooks
     */
    public static function init() {
        // Auto-provision admin dashboard page on init
        add_action( 'init', [ __CLASS__, 'provision_dashboard_page' ] );

        // Dedicated template loader
        add_filter( 'template_include', [ __CLASS__, 'route_dashboard_template' ] );

        // Handle Admin Login submission
        add_action( 'init', [ __CLASS__, 'handle_admin_login' ] );

        // Handle Admin Logout
        add_action( 'init', [ __CLASS__, 'handle_admin_logout' ] );

        // AJAX Product Management Endpoints
        add_action( 'wp_ajax_essenza_admin_save_product', [ __CLASS__, 'ajax_save_product' ] );
        add_action( 'wp_ajax_essenza_admin_delete_product', [ __CLASS__, 'ajax_delete_product' ] );
        add_action( 'wp_ajax_essenza_admin_toggle_visibility', [ __CLASS__, 'ajax_toggle_visibility' ] );
        add_action( 'wp_ajax_essenza_admin_toggle_featured', [ __CLASS__, 'ajax_toggle_featured' ] );
        add_action( 'wp_ajax_essenza_admin_add_category', [ __CLASS__, 'ajax_add_category' ] );
        add_action( 'wp_ajax_essenza_admin_get_product_data', [ __CLASS__, 'ajax_get_product_data' ] );
    }

    /**
     * Check if the current user has admin/management capability
     */
    public static function is_admin_authorized() {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        return current_user_can( 'manage_woocommerce' ) || 
               current_user_can( 'edit_products' ) || 
               current_user_can( 'administrator' );
    }

    /**
     * Ensure the /admin-dashboard/ page exists in the database
     */
    public static function provision_dashboard_page() {
        $page = get_page_by_path( self::PAGE_SLUG );
        if ( ! $page ) {
            $page_id = wp_insert_post( [
                'post_title'     => 'Essenza Admin Suite',
                'post_name'      => self::PAGE_SLUG,
                'post_status'    => 'publish',
                'post_type'      => 'page',
                'comment_status' => 'closed',
            ] );

            if ( $page_id && ! is_wp_error( $page_id ) ) {
                update_post_meta( $page_id, '_wp_page_template', 'template-admin-dashboard.php' );
            }
        }
    }

    /**
     * Intercept and load the custom dashboard template
     */
    public static function route_dashboard_template( $template ) {
        if ( is_page( self::PAGE_SLUG ) || ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/' . self::PAGE_SLUG ) !== false ) ) {
            $custom_template = get_template_directory() . '/template-admin-dashboard.php';
            if ( file_exists( $custom_template ) ) {
                return $custom_template;
            }
        }
        return $template;
    }

    /**
     * Handle Admin Login
     */
    public static function handle_admin_login() {
        if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' || ! isset( $_POST['essenza_admin_login_action'] ) ) {
            return;
        }

        if ( ! isset( $_POST['essenza_login_nonce'] ) || ! wp_verify_nonce( $_POST['essenza_login_nonce'], 'essenza_admin_login' ) ) {
            wp_die( esc_html__( 'Security check failed. Please refresh and try again.', 'essenza-theme' ) );
        }

        $username = sanitize_user( wp_unslash( $_POST['username'] ?? '' ) );
        $password = $_POST['password'] ?? '';
        $remember = ! empty( $_POST['remember'] );

        $creds = [
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => $remember,
        ];

        $user = wp_signon( $creds, is_ssl() );

        if ( is_wp_error( $user ) ) {
            set_transient( 'essenza_login_error', $user->get_error_message(), 30 );
            wp_safe_redirect( home_url( '/' . self::PAGE_SLUG . '/?error=invalid_credentials' ) );
            exit;
        }

        // Verify authorized role
        if ( ! user_can( $user, 'manage_woocommerce' ) && ! user_can( $user, 'edit_products' ) && ! user_can( $user, 'administrator' ) ) {
            wp_logout();
            set_transient( 'essenza_login_error', __( 'Access Denied: Administrative permissions required to access the inventory suite.', 'essenza-theme' ), 30 );
            wp_safe_redirect( home_url( '/' . self::PAGE_SLUG . '/?error=forbidden' ) );
            exit;
        }

        wp_safe_redirect( home_url( '/' . self::PAGE_SLUG . '/' ) );
        exit;
    }

    /**
     * Handle Admin Logout
     */
    public static function handle_admin_logout() {
        if ( isset( $_GET['action'] ) && 'essenza_logout' === $_GET['action'] ) {
            if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'essenza_admin_logout' ) ) {
                wp_logout();
                wp_safe_redirect( home_url( '/' . self::PAGE_SLUG . '/?logged_out=1' ) );
                exit;
            }
        }
    }

    /**
     * AJAX: Save Product (Create & Update)
     */
    public static function ajax_save_product() {
        check_ajax_referer( 'essenza_admin_nonce', 'security' );

        if ( ! self::is_admin_authorized() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized: You do not have permission to manage products.', 'essenza-theme' ) ], 403 );
        }

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $title      = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
        $slug       = sanitize_title( wp_unslash( $_POST['slug'] ?? '' ) );
        $desc       = wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) );
        $short_desc = wp_kses_post( wp_unslash( $_POST['short_description'] ?? '' ) );
        $reg_price  = sanitize_text_field( wp_unslash( $_POST['regular_price'] ?? '' ) );
        $sale_price = sanitize_text_field( wp_unslash( $_POST['sale_price'] ?? '' ) );
        $sku        = sanitize_text_field( wp_unslash( $_POST['sku'] ?? '' ) );
        $stock_status = in_array( $_POST['stock_status'] ?? '', [ 'instock', 'outofstock', 'onbackorder' ], true ) ? $_POST['stock_status'] : 'instock';
        $manage_stock = ! empty( $_POST['manage_stock'] );
        $stock_qty  = isset( $_POST['stock_quantity'] ) && '' !== $_POST['stock_quantity'] ? intval( $_POST['stock_quantity'] ) : null;
        $status     = in_array( $_POST['status'] ?? '', [ 'publish', 'draft' ], true ) ? $_POST['status'] : 'publish';
        $featured   = ! empty( $_POST['is_featured'] );

        // Essenza Supply Chain Metadata
        $origin     = in_array( $_POST['origin_type'] ?? '', [ 'in_house', 'local_supplier', 'overseas_import' ], true ) ? $_POST['origin_type'] : 'in_house';
        $lead_label = sanitize_text_field( wp_unslash( $_POST['lead_time_label'] ?? '' ) );
        $lead_min   = isset( $_POST['lead_time_min_days'] ) ? absint( $_POST['lead_time_min_days'] ) : 1;
        $lead_max   = isset( $_POST['lead_time_max_days'] ) ? absint( $_POST['lead_time_max_days'] ) : 3;
        $freight_tier = sanitize_text_field( wp_unslash( $_POST['freight_tier'] ?? 'standard_parcel' ) );
        $cubic_m3   = isset( $_POST['cubic_meters'] ) ? floatval( $_POST['cubic_meters'] ) : 0.0;

        // Categories
        $categories = isset( $_POST['categories'] ) ? array_map( 'absint', (array) $_POST['categories'] ) : [];

        // Basic Validation
        if ( empty( $title ) ) {
            wp_send_json_error( [ 'message' => __( 'Product name is required.', 'essenza-theme' ) ] );
        }

        if ( '' === $reg_price || ! is_numeric( $reg_price ) || floatval( $reg_price ) < 0 ) {
            wp_send_json_error( [ 'message' => __( 'A valid regular price (AUD) is required.', 'essenza-theme' ) ] );
        }

        if ( '' !== $sale_price && ( ! is_numeric( $sale_price ) || floatval( $sale_price ) < 0 ) ) {
            wp_send_json_error( [ 'message' => __( 'Sale price must be a valid positive number.', 'essenza-theme' ) ] );
        }

        // Post array for WordPress
        $post_data = [
            'post_title'   => $title,
            'post_content' => $desc,
            'post_excerpt' => $short_desc,
            'post_status'  => $status,
            'post_type'    => 'product',
        ];

        if ( ! empty( $slug ) ) {
            $post_data['post_name'] = $slug;
        }

        if ( $product_id > 0 ) {
            $post_data['ID'] = $product_id;
            $updated_id = wp_update_post( $post_data, true );
            if ( is_wp_error( $updated_id ) ) {
                wp_send_json_error( [ 'message' => $updated_id->get_error_message() ] );
            }
        } else {
            $product_id = wp_insert_post( $post_data, true );
            if ( is_wp_error( $product_id ) ) {
                wp_send_json_error( [ 'message' => $product_id->get_error_message() ] );
            }
            // Set simple product taxonomy
            wp_set_object_terms( $product_id, 'simple', 'product_type' );
        }

        // Handle Image Upload
        if ( ! empty( $_FILES['product_image']['name'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';

            // Validate file extension and MIME type
            $allowed_mimes = [
                'jpg|jpeg|jpe' => 'image/jpeg',
                'png'          => 'image/png',
                'webp'         => 'image/webp',
            ];

            $file_info = wp_check_filetype( $_FILES['product_image']['name'], $allowed_mimes );
            if ( ! $file_info['ext'] ) {
                wp_send_json_error( [ 'message' => __( 'Invalid image type. Only JPG, PNG, and WebP images are allowed.', 'essenza-theme' ) ] );
            }

            // Max file size: 8MB
            if ( $_FILES['product_image']['size'] > 8 * 1024 * 1024 ) {
                wp_send_json_error( [ 'message' => __( 'Image file size exceeds the 8MB limit.', 'essenza-theme' ) ] );
            }

            $attachment_id = media_handle_upload( 'product_image', $product_id );
            if ( is_wp_error( $attachment_id ) ) {
                wp_send_json_error( [ 'message' => 'Upload failed: ' . $attachment_id->get_error_message() ] );
            } else {
                set_post_thumbnail( $product_id, $attachment_id );
            }
        } elseif ( isset( $_POST['remove_image'] ) && '1' === $_POST['remove_image'] ) {
            delete_post_thumbnail( $product_id );
        }

        // Update WooCommerce Product Properties
        $product = wc_get_product( $product_id );
        if ( $product ) {
            $product->set_regular_price( wc_format_decimal( $reg_price ) );
            if ( '' !== $sale_price ) {
                $product->set_sale_price( wc_format_decimal( $sale_price ) );
                $product->set_price( wc_format_decimal( $sale_price ) );
            } else {
                $product->set_sale_price( '' );
                $product->set_price( wc_format_decimal( $reg_price ) );
            }

            if ( ! empty( $sku ) ) {
                $product->set_sku( $sku );
            }

            $product->set_stock_status( $stock_status );
            $product->set_manage_stock( $manage_stock );
            if ( $manage_stock && null !== $stock_qty ) {
                $product->set_stock_quantity( $stock_qty );
            }

            $product->set_featured( $featured );
            $product->save();
        }

        // Set Categories
        if ( ! empty( $categories ) ) {
            wp_set_object_terms( $product_id, $categories, 'product_cat' );
        } else {
            // Default to uncategorized if none selected
            $default_cat = get_option( 'default_product_cat' );
            if ( $default_cat ) {
                wp_set_object_terms( $product_id, [ (int) $default_cat ], 'product_cat' );
            }
        }

        // Update Supply Chain & Origin Metadata
        update_post_meta( $product_id, '_essenza_origin_type', $origin );
        update_post_meta( $product_id, '_essenza_lead_time_label', $lead_label );
        update_post_meta( $product_id, '_essenza_lead_time_min_days', $lead_min );
        update_post_meta( $product_id, '_essenza_lead_time_max_days', $lead_max );
        update_post_meta( $product_id, '_essenza_freight_tier', $freight_tier );
        update_post_meta( $product_id, '_essenza_cubic_meters', $cubic_m3 );

        // Clear Transients
        wc_delete_product_transients( $product_id );

        // Fetch refreshed product row data for seamless client-side update
        $row_data = self::get_product_table_payload( $product_id );

        wp_send_json_success( [
            'message' => __( 'Product saved and synchronized successfully!', 'essenza-theme' ),
            'product' => $row_data,
        ] );
    }

    /**
     * AJAX: Delete Product
     */
    public static function ajax_delete_product() {
        check_ajax_referer( 'essenza_admin_nonce', 'security' );

        if ( ! self::is_admin_authorized() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized: You do not have permission to delete products.', 'essenza-theme' ) ], 403 );
        }

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        if ( ! $product_id || get_post_type( $product_id ) !== 'product' ) {
            wp_send_json_error( [ 'message' => __( 'Invalid product ID.', 'essenza-theme' ) ] );
        }

        $deleted = wp_delete_post( $product_id, true );
        if ( ! $deleted ) {
            wp_send_json_error( [ 'message' => __( 'Could not delete product.', 'essenza-theme' ) ] );
        }

        wp_send_json_success( [
            'message'    => __( 'Product permanently removed from catalog.', 'essenza-theme' ),
            'product_id' => $product_id,
        ] );
    }

    /**
     * AJAX: Quick Toggle Visibility (Publish / Draft)
     */
    public static function ajax_toggle_visibility() {
        check_ajax_referer( 'essenza_admin_nonce', 'security' );

        if ( ! self::is_admin_authorized() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'essenza-theme' ) ], 403 );
        }

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $current    = get_post_status( $product_id );
        $new_status = ( 'publish' === $current ) ? 'draft' : 'publish';

        wp_update_post( [
            'ID'          => $product_id,
            'post_status' => $new_status,
        ] );

        wc_delete_product_transients( $product_id );

        wp_send_json_success( [
            'new_status' => $new_status,
            'label'      => ( 'publish' === $new_status ) ? __( 'Published', 'essenza-theme' ) : __( 'Draft', 'essenza-theme' ),
            'message'    => sprintf( __( 'Visibility changed to %s', 'essenza-theme' ), ucfirst( $new_status ) ),
        ] );
    }

    /**
     * AJAX: Quick Toggle Featured Status
     */
    public static function ajax_toggle_featured() {
        check_ajax_referer( 'essenza_admin_nonce', 'security' );

        if ( ! self::is_admin_authorized() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'essenza-theme' ) ], 403 );
        }

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $product    = wc_get_product( $product_id );

        if ( ! $product ) {
            wp_send_json_error( [ 'message' => __( 'Product not found.', 'essenza-theme' ) ] );
        }

        $is_featured = ! $product->is_featured();
        $product->set_featured( $is_featured );
        $product->save();

        wc_delete_product_transients( $product_id );

        wp_send_json_success( [
            'is_featured' => $is_featured,
            'message'     => $is_featured ? __( 'Marked as Featured on Homepage', 'essenza-theme' ) : __( 'Removed from Featured list', 'essenza-theme' ),
        ] );
    }

    /**
     * AJAX: Add New Category on the fly
     */
    public static function ajax_add_category() {
        check_ajax_referer( 'essenza_admin_nonce', 'security' );

        if ( ! self::is_admin_authorized() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'essenza-theme' ) ], 403 );
        }

        $name = sanitize_text_field( wp_unslash( $_POST['cat_name'] ?? '' ) );
        if ( empty( $name ) ) {
            wp_send_json_error( [ 'message' => __( 'Category name cannot be empty.', 'essenza-theme' ) ] );
        }

        $term = wp_insert_term( $name, 'product_cat' );
        if ( is_wp_error( $term ) ) {
            wp_send_json_error( [ 'message' => $term->get_error_message() ] );
        }

        wp_send_json_success( [
            'term_id' => $term['term_id'],
            'name'    => $name,
            'message' => sprintf( __( 'Category "%s" created successfully.', 'essenza-theme' ), $name ),
        ] );
    }

    /**
     * AJAX: Retrieve single product full dataset for editing modal
     */
    public static function ajax_get_product_data() {
        check_ajax_referer( 'essenza_admin_nonce', 'security' );

        if ( ! self::is_admin_authorized() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'essenza-theme' ) ], 403 );
        }

        $product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
        $product    = wc_get_product( $product_id );

        if ( ! $product ) {
            wp_send_json_error( [ 'message' => __( 'Product not found.', 'essenza-theme' ) ] );
        }

        $post = get_post( $product_id );
        $img_id = get_post_thumbnail_id( $product_id );
        $img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'large' ) : '';

        $cats = wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'ids' ] );

        $data = [
            'id'                => $product_id,
            'title'             => $product->get_name(),
            'slug'              => $post->post_name,
            'description'       => $post->post_content,
            'short_description' => $post->post_excerpt,
            'regular_price'     => $product->get_regular_price(),
            'sale_price'        => $product->get_sale_price(),
            'sku'               => $product->get_sku(),
            'stock_status'      => $product->get_stock_status(),
            'manage_stock'      => $product->get_manage_stock(),
            'stock_quantity'    => $product->get_stock_quantity(),
            'status'            => $post->post_status,
            'is_featured'       => $product->is_featured(),
            'image_url'         => $img_url,
            'categories'        => $cats,
            'origin_type'       => get_post_meta( $product_id, '_essenza_origin_type', true ) ?: 'in_house',
            'lead_time_label'   => get_post_meta( $product_id, '_essenza_lead_time_label', true ) ?: '',
            'lead_time_min_days'=> get_post_meta( $product_id, '_essenza_lead_time_min_days', true ) ?: 1,
            'lead_time_max_days'=> get_post_meta( $product_id, '_essenza_lead_time_max_days', true ) ?: 3,
            'freight_tier'      => get_post_meta( $product_id, '_essenza_freight_tier', true ) ?: 'standard_parcel',
            'cubic_meters'      => get_post_meta( $product_id, '_essenza_cubic_meters', true ) ?: 0.0,
        ];

        wp_send_json_success( $data );
    }

    /**
     * Helper to prepare table row JSON payload
     */
    public static function get_product_table_payload( $product_id ) {
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return null;
        }

        $post = get_post( $product_id );
        $img_id = get_post_thumbnail_id( $product_id );
        $img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'thumbnail' ) : '';

        $cats = wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'names' ] );
        $cat_list = ! empty( $cats ) ? implode( ', ', $cats ) : 'Uncategorized';

        $origin = get_post_meta( $product_id, '_essenza_origin_type', true ) ?: 'in_house';

        return [
            'id'           => $product_id,
            'title'        => $product->get_name(),
            'sku'          => $product->get_sku() ?: '—',
            'permalink'    => get_permalink( $product_id ),
            'image_url'    => $img_url,
            'categories'   => $cat_list,
            'price_html'   => $product->get_price_html(),
            'regular_price'=> $product->get_regular_price(),
            'sale_price'   => $product->get_sale_price(),
            'stock_status' => $product->get_stock_status(),
            'status'       => $post->post_status,
            'is_featured'  => $product->is_featured(),
            'origin'       => $origin,
            'origin_badge' => essenza_get_product_origin_badge( $product_id ),
        ];
    }
}

// Boot Controller
Essenza_Admin_Dashboard::init();
