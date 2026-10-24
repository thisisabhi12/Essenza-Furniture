<?php
/**
 * Essenza Furniture - Standard Page Template
 * Wraps WooCommerce Cart, Checkout, and My Account pages within the luxury container.
 *
 * @package Essenza_Theme
 */

get_header(); ?>

<main id="primary" class="site-main">
    <div class="essenza-container" style="padding-top:60px;padding-bottom:100px;">
        <?php
        while ( have_posts() ) :
            the_post();
            ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <?php if ( ! is_cart() && ! is_checkout() && ! is_account_page() ) : ?>
                    <header class="entry-header" style="margin-bottom:30px;">
                        <h1 class="entry-title" style="font-family:var(--font-serif);font-size:40px;color:var(--color-dark);">
                            <?php the_title(); ?>
                        </h1>
                    </header>
                <?php endif; ?>

                <div class="entry-content">
                    <?php the_content(); ?>
                </div>
            </article>
            <?php
        endwhile;
        ?>
    </div>
</main>

<?php get_footer(); ?>
