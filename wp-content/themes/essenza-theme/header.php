<?php
/**
 * Essenza Furniture Luxury Theme - Header Template
 *
 * @package Essenza_Theme
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="profile" href="https://gmpg.org/xfn/11">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<!-- Announcement Bar -->
<div class="essenza-announcement-bar">
    <span>Sydney Warehouse &bull; Melbourne Artisans &bull; Direct Italian Sourcing</span>
    <span class="accent">&bull;</span>
    <span>Complimentary White-Glove Delivery Across Metro Australia on Orders Over $2,500 AUD</span>
</div>

<!-- Main Sticky Header -->
<header class="essenza-header" id="masthead">
    <div class="essenza-container essenza-header-inner">
        
        <!-- Luxury Brand Logo -->
        <div class="essenza-logo">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
                ESSENZA
            </a>
            <span class="tagline">FURNITURE &bull; AUSTRALIA</span>
        </div>

        <!-- Primary Luxury Navigation -->
        <nav class="essenza-nav" aria-label="<?php esc_attr_e( 'Primary Menu', 'essenza-theme' ); ?>">
            <a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">All Furniture</a>
            <a href="<?php echo esc_url( home_url( '/#living' ) ); ?>">Living</a>
            <a href="<?php echo esc_url( home_url( '/#dining' ) ); ?>">Dining</a>
            <a href="<?php echo esc_url( home_url( '/#bespoke' ) ); ?>">Bespoke Orders</a>
            <a href="<?php echo esc_url( home_url( '/#sourcing' ) ); ?>">Sourcing Model</a>
        </nav>

        <!-- Right User Actions (Account & Cart Trigger) -->
        <div class="essenza-actions">
            <?php if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'administrator' ) ) : ?>
                <a href="<?php echo esc_url( home_url( '/admin-dashboard/' ) ); ?>" class="essenza-action-btn" style="color:var(--color-primary);border:1px solid rgba(197,168,128,0.4);border-radius:4px;padding:6px 12px;background:rgba(197,168,128,0.08);" title="<?php esc_attr_e( 'Open Admin Inventory Suite', 'essenza-theme' ); ?>">
                    <span style="font-size:13px;font-weight:600;">⚙️ Admin Suite</span>
                </a>
            <?php endif; ?>

            <!-- Account Button -->
            <a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="essenza-action-btn" title="<?php esc_attr_e( 'My Account', 'essenza-theme' ); ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <span>Account</span>
            </a>

            <!-- Cart Trigger Button -->
            <button type="button" class="essenza-action-btn essenza-cart-trigger" id="essenza-cart-toggle" aria-label="<?php esc_attr_e( 'Open Cart Drawer', 'essenza-theme' ); ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <path d="M16 10a4 4 0 0 1-8 0"></path>
                </svg>
                <span>Bag</span>
                <span class="essenza-cart-count"><?php echo WC()->cart ? esc_html( WC()->cart->get_cart_contents_count() ) : '0'; ?></span>
            </button>
        </div>

    </div>
</header>

<!-- Off-Canvas Cart Backdrop -->
<div class="essenza-drawer-backdrop" id="essenza-drawer-backdrop"></div>

<!-- Off-Canvas Cart Drawer -->
<div class="essenza-cart-drawer" id="essenza-cart-drawer" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Shopping Bag', 'essenza-theme' ); ?>">
    <div class="essenza-drawer-header">
        <h3>Shopping Bag</h3>
        <button type="button" class="essenza-drawer-close" id="essenza-drawer-close" aria-label="<?php esc_attr_e( 'Close Bag', 'essenza-theme' ); ?>">&times;</button>
    </div>
    <div class="essenza-drawer-body">
        <div class="widget_shopping_cart_content">
            <?php woocommerce_mini_cart(); ?>
        </div>
    </div>
</div>
