<?php
/**
 * Essenza Furniture - Main Index Fallback Template
 *
 * @package Essenza_Theme
 */

get_header(); ?>

<main id="primary" class="site-main">
    <div class="essenza-container" style="padding-top:60px;padding-bottom:80px;">
        <?php
        if ( have_posts() ) :
            while ( have_posts() ) :
                the_post();
                ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class(); ?> style="margin-bottom:40px;">
                    <h1 class="entry-title" style="font-family:var(--font-serif);font-size:36px;margin-bottom:20px;">
                        <?php the_title(); ?>
                    </h1>
                    <div class="entry-content">
                        <?php the_content(); ?>
                    </div>
                </article>
                <?php
            endwhile;
        else :
            ?>
            <p><?php esc_html_e( 'No content found.', 'essenza-theme' ); ?></p>
            <?php
        endif;
        ?>
    </div>
</main>

<?php get_footer(); ?>
