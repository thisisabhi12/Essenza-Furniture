<?php
/**
 * Template Name: Essenza Luxury Home
 * Description: High-end luxury homepage template for Essenza Furniture with Hero, 4-column curated catalog query, and split bestseller editorial banner.
 *
 * @package Essenza_Theme
 */

get_header(); ?>

<main id="primary" class="site-main">

    <!-- 1. EDITORIAL LUXURY HERO -->
    <section class="essenza-hero">
        <div class="essenza-container">
            <div class="essenza-hero-grid">
                
                <!-- Hero Content -->
                <div class="essenza-hero-content">
                    <div class="essenza-hero-badge">
                        <span>✨</span>
                        <span>2026 Architectural Collection</span>
                    </div>
                    
                    <h1 class="essenza-hero-title">
                        Contemporary Italian Elegance, Crafted for Australian Living.
                    </h1>
                    
                    <p class="essenza-hero-subtitle">
                        Discover sculptural modular seating, hand-selected marble, and solid timber statement pieces. Seamlessly order in-stock warehouse items, domestic artisan builds, and custom overseas imports in one single checkout.
                    </p>

                    <div class="essenza-hero-cta">
                        <a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="btn-primary">
                            <span>Explore Catalog</span>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </a>
                        <a href="#sourcing" class="btn-secondary">
                            <span>Our Sourcing Model</span>
                        </a>
                    </div>
                </div>

                <!-- Hero Visual -->
                <div class="essenza-hero-visual">
                    <div class="essenza-hero-image-wrap">
                        <img src="https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=1200&q=80" alt="Essenza Luxury Furniture Living Collection" loading="eager">
                    </div>

                    <!-- Floating Supply Chain Transparency Badge -->
                    <div class="essenza-hero-floating-card">
                        <div class="icon">📦</div>
                        <div class="text">
                            <strong>Multi-Origin Delivery</strong>
                            <span>Real-time tracking per line item across Australia</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- 2. VALUE PROPOSITION PILLARS -->
    <section class="essenza-pillars">
        <div class="essenza-container">
            <div class="essenza-pillars-grid">
                
                <div class="essenza-pillar-item">
                    <div class="essenza-pillar-icon">⚡</div>
                    <div class="essenza-pillar-text">
                        <h4>Sydney Warehouse Stock</h4>
                        <p>1–2 business day dispatch on readily available pieces</p>
                    </div>
                </div>

                <div class="essenza-pillar-item">
                    <div class="essenza-pillar-icon">🏭</div>
                    <div class="essenza-pillar-text">
                        <h4>Local Timber Artisans</h4>
                        <p>Special orders hand-built in Melbourne & Sydney workshops</p>
                    </div>
                </div>

                <div class="essenza-pillar-item">
                    <div class="essenza-pillar-icon">🚢</div>
                    <div class="essenza-pillar-text">
                        <h4>Custom Container Imports</h4>
                        <p>Sea freight tracking from factory dock to Sydney port</p>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- 3. LIVING COLLECTION GRID (#living) -->
    <section class="essenza-section" id="living">
        <div class="essenza-container">
            
            <div class="essenza-section-header">
                <div>
                    <span class="essenza-section-tag">Living Collection</span>
                    <h2 class="essenza-section-title">Curated Living & Modular Seating</h2>
                </div>
                <a href="<?php echo esc_url( get_term_link( 'living', 'product_cat' ) ); ?>" class="essenza-section-link">
                    <span>View All Living Pieces</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </a>
            </div>

            <!-- Loop Living Products -->
            <div class="essenza-product-grid">
                <?php
                $living_query = new WP_Query( [
                    'post_type'      => 'product',
                    'posts_per_page' => 8,
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                    'tax_query'      => [
                        [
                            'taxonomy' => 'product_cat',
                            'field'    => 'slug',
                            'terms'    => 'living',
                        ],
                    ],
                ] );

                if ( $living_query->have_posts() ) :
                    while ( $living_query->have_posts() ) :
                        $living_query->the_post();
                        wc_get_template_part( 'content', 'product' );
                    endwhile;
                    wp_reset_postdata();
                else :
                    echo '<p>' . esc_html__( 'No living products found.', 'essenza-theme' ) . '</p>';
                endif;
                ?>
            </div>

        </div>
    </section>

    <!-- 4. SPLIT BESTSELLER EDITORIAL BANNER (#bespoke) -->
    <section class="essenza-editorial-banner" id="bespoke">
        <div class="essenza-editorial-grid">
            
            <!-- Left Editorial Text -->
            <div class="essenza-editorial-content">
                <span class="tag">Bespoke Manufacturing & Trade Program</span>
                <h2>Architectural Furniture Without Middleman Markups.</h2>
                <p>
                    Every sofa, dining suite, and credenza is engineered with kiln-dried timber frames and commercial-grade fabrics. By maintaining direct relationships with local Melbourne joiners and international container freight, Essenza passes uncompromised luxury directly to Australian homes.
                </p>
                <div style="display:flex;gap:16px;flex-wrap:wrap;">
                    <a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="btn-primary">
                        <span>Browse Trade Pieces</span>
                    </a>
                    <a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="btn-secondary">
                        <span>Track an Existing Order</span>
                    </a>
                </div>
            </div>

            <!-- Right Visual Image -->
            <div class="essenza-editorial-image">
                <img src="https://images.unsplash.com/photo-1618221195710-dd6b41faaea6?auto=format&fit=crop&w=1200&q=80" alt="Essenza Furniture Bespoke Architecture" loading="lazy">
            </div>

        </div>
    </section>

    <!-- 5. DINING COLLECTION GRID (#dining) -->
    <section class="essenza-section" id="dining" style="background-color:#FDFDFD;border-top:1px solid var(--color-border);border-bottom:1px solid var(--color-border);">
        <div class="essenza-container">
            
            <div class="essenza-section-header">
                <div>
                    <span class="essenza-section-tag">Dining Collection</span>
                    <h2 class="essenza-section-title">Architectural Dining & Entertaining</h2>
                </div>
                <a href="<?php echo esc_url( get_term_link( 'dining', 'product_cat' ) ); ?>" class="essenza-section-link">
                    <span>View All Dining Pieces</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </a>
            </div>

            <!-- Loop Dining Products -->
            <div class="essenza-product-grid">
                <?php
                $dining_query = new WP_Query( [
                    'post_type'      => 'product',
                    'posts_per_page' => 8,
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                    'tax_query'      => [
                        [
                            'taxonomy' => 'product_cat',
                            'field'    => 'slug',
                            'terms'    => 'dining',
                        ],
                    ],
                ] );

                if ( $dining_query->have_posts() ) :
                    while ( $dining_query->have_posts() ) :
                        $dining_query->the_post();
                        wc_get_template_part( 'content', 'product' );
                    endwhile;
                    wp_reset_postdata();
                else :
                    echo '<p>' . esc_html__( 'No dining products found.', 'essenza-theme' ) . '</p>';
                endif;
                ?>
            </div>

        </div>
    </section>

    <!-- 5. SUPPLY CHAIN SOURCING MODEL EXPLANATION -->
    <section class="essenza-section" id="sourcing" style="background-color:#FAF9F6;border-bottom:1px solid var(--color-border);">
        <div class="essenza-container">
            <div class="essenza-section-header" style="text-align:center;display:block;margin-bottom:60px;">
                <span class="essenza-section-tag">Unified Commercial Guarantee</span>
                <h2 class="essenza-section-title">How Essenza Multi-Origin Delivery Works</h2>
                <p style="color:var(--color-text-muted);max-width:600px;margin:12px auto 0;">
                    You pay once at checkout in Australian Dollars. Behind the scenes, our intelligent fulfillment engine dispatches each piece directly from its optimal origin.
                </p>
            </div>

            <div class="essenza-pillars-grid">
                <div style="background:#FFFFFF;padding:32px;border-radius:8px;border:1px solid var(--color-border);box-shadow:var(--shadow-sm);">
                    <div style="font-size:32px;margin-bottom:16px;">🟢</div>
                    <h3 style="font-size:18px;margin-bottom:8px;font-family:var(--font-serif);">1. In-House Warehouse</h3>
                    <p style="font-size:14px;color:var(--color-text-muted);line-height:1.6;">
                        Readily packed and dispatched within 24–48 hours from our Sydney distribution centre via Australia Post or StarTrack courier.
                    </p>
                </div>

                <div style="background:#FFFFFF;padding:32px;border-radius:8px;border:1px solid var(--color-border);box-shadow:var(--shadow-sm);">
                    <div style="font-size:32px;margin-bottom:16px;">🔵</div>
                    <h3 style="font-size:18px;margin-bottom:8px;font-family:var(--font-serif);">2. Australian Local Makers</h3>
                    <p style="font-size:14px;color:var(--color-text-muted);line-height:1.6;">
                        Procured on demand from domestic timber craftsmen. Dispatches in 2–3 weeks directly via specialized bulky freight carriers.
                    </p>
                </div>

                <div style="background:#FFFFFF;padding:32px;border-radius:8px;border:1px solid var(--color-border);box-shadow:var(--shadow-sm);">
                    <div style="font-size:32px;margin-bottom:16px;">🟡</div>
                    <h3 style="font-size:18px;margin-bottom:8px;font-family:var(--font-serif);">3. Custom Container Imports</h3>
                    <p style="font-size:14px;color:var(--color-text-muted);line-height:1.6;">
                        Made-to-order international pieces. Receive sea-freight milestone updates until final mile white-glove arrival at your home.
                    </p>
                </div>
            </div>
        </div>
    </section>

</main>

<?php get_footer(); ?>
