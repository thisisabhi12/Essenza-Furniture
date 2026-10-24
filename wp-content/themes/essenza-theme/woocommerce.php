<?php
/**
 * Essenza Furniture - Dedicated WooCommerce Wrapper
 * Renders the Shop catalog, category taxonomies, and single product displays inside the luxury container.
 *
 * @package Essenza_Theme
 */

get_header(); ?>

<main id="primary" class="site-main">
    <div class="essenza-container" style="padding-top:60px;padding-bottom:100px;">
        <?php woocommerce_content(); ?>
    </div>
</main>

<?php get_footer(); ?>
