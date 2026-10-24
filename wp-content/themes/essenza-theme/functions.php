<?php
/**
 * Essenza Furniture Luxury Theme - Core Functions
 *
 * @package Essenza_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Theme Setup & WooCommerce Support
 */
function essenza_theme_setup() {
    // Document title tag management
    add_theme_support( 'title-tag' );

    // Featured images support
    add_theme_support( 'post-thumbnails' );

    // HTML5 markup support
    add_theme_support( 'html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ] );

    // Core WooCommerce Theme Support
    add_theme_support( 'woocommerce', [
        'thumbnail_image_width' => 600,
        'single_image_width'    => 1000,
        'product_grid'          => [
            'default_rows'    => 4,
            'min_rows'        => 2,
            'max_rows'        => 8,
            'default_columns' => 4,
            'min_columns'     => 1,
            'max_columns'     => 4,
        ],
    ] );

    // Modern WooCommerce Product Gallery Zoom, Lightbox, and Slider
    add_theme_support( 'wc-product-gallery-zoom' );
    add_theme_support( 'wc-product-gallery-lightbox' );
    add_theme_support( 'wc-product-gallery-slider' );

    // Register Nav Menus
    register_nav_menus( [
        'primary' => __( 'Primary Luxury Navigation', 'essenza-theme' ),
        'footer'  => __( 'Footer Navigation', 'essenza-theme' ),
    ] );
}
add_action( 'after_setup_theme', 'essenza_theme_setup' );

/**
 * Strip Default Cluttered WooCommerce Hooks (Breadcrumbs, Sidebar)
 */
function essenza_clean_woocommerce_defaults() {
    // Remove default breadcrumbs
    remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );

    // Remove default sidebar
    remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );
}
add_action( 'init', 'essenza_clean_woocommerce_defaults' );

/**
 * Enqueue Luxury Fonts, Styles, and Scripts
 */
function essenza_enqueue_assets() {
    // 1. Google Fonts: Cormorant Garamond (Editorial Serif) & Plus Jakarta Sans (Clean Modern Sans)
    wp_enqueue_style(
        'essenza-google-fonts',
        'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap',
        [],
        null
    );

    // 2. Main Theme Stylesheet
    wp_enqueue_style(
        'essenza-theme-style',
        get_stylesheet_uri(),
        [ 'essenza-google-fonts' ],
        '1.0.0'
    );

    // 3. Theme JavaScript & Off-Canvas Cart Controller
    wp_enqueue_script(
        'essenza-main-js',
        get_template_directory_uri() . '/assets/js/main.js',
        [ 'jquery' ],
        '1.0.0',
        true
    );

    // Localize Script for AJAX Cart Interaction
    wp_localize_script( 'essenza-main-js', 'essenza_params', [
        'ajax_url'        => admin_url( 'admin-ajax.php' ),
        'wc_ajax_url'     => WC_AJAX::get_endpoint( '%%endpoint%%' ),
        'cart_url'        => wc_get_cart_url(),
        'checkout_url'    => wc_get_checkout_url(),
        'is_cart'         => is_cart(),
        'is_checkout'     => is_checkout(),
    ] );
}
add_action( 'wp_enqueue_scripts', 'essenza_enqueue_assets' );

/**
 * AJAX Mini-Cart Fragments Refresh
 * Updates both the cart counter and the drawer contents dynamically when items are added.
 */
function essenza_cart_fragments( $fragments ) {
    $cart_count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;

    // Refresh Counter Badge
    $fragments['span.essenza-cart-count'] = '<span class="essenza-cart-count">' . esc_html( $cart_count ) . '</span>';

    // Refresh Drawer Mini-Cart HTML
    ob_start();
    echo '<div class="widget_shopping_cart_content">';
    woocommerce_mini_cart();
    echo '</div>';
    $fragments['div.widget_shopping_cart_content'] = ob_get_clean();

    return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'essenza_cart_fragments' );

/**
 * Helper: Render Sourcing Origin Badge (Inherited from essenza-fulfillment-core plugin)
 */
function essenza_get_product_origin_badge( $product_id ) {
    $origin = get_post_meta( $product_id, '_essenza_origin_type', true ) ?: 'in_house';
    $label  = get_post_meta( $product_id, '_essenza_lead_time_label', true );

    $origin_name = match ( $origin ) {
        'overseas_import' => __( 'Custom Import', 'essenza-theme' ),
        'local_supplier'  => __( 'Local Workshop', 'essenza-theme' ),
        default           => __( 'In Stock', 'essenza-theme' ),
    };

    $icon = match ( $origin ) {
        'overseas_import' => '🚢',
        'local_supplier'  => '🏭',
        default           => '⚡',
    };

    return sprintf(
        '<span class="essenza-origin-badge origin-%s">%s %s</span>',
        esc_attr( $origin ),
        esc_html( $icon ),
        esc_html( $origin_name )
    );
}

/**
 * Load Enterprise Admin Dashboard Management Suite
 */
require_once get_template_directory() . '/inc/class-essenza-admin-dashboard.php';
