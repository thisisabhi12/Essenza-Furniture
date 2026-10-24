<?php
/**
 * Essenza Furniture - Custom Product Card Loop
 *
 * @package Essenza_Theme
 * @see     https://woocommerce.com/document/template-structure/
 */

defined( 'ABSPATH' ) || exit;

global $product;

// Ensure visibility
if ( empty( $product ) || ! $product->is_visible() ) {
    return;
}

$product_id = $product->get_id();
$permalink  = get_permalink( $product_id );
$title      = $product->get_name();
$price_html = $product->get_price_html();

// Sourcing Origin & Lead-Time Metadata (from essenza-fulfillment-core)
$origin     = get_post_meta( $product_id, '_essenza_origin_type', true ) ?: 'in_house';
$lead_label = get_post_meta( $product_id, '_essenza_lead_time_label', true );

if ( empty( $lead_label ) ) {
    $lead_label = match ( $origin ) {
        'overseas_import' => __( 'Estimated 8–10 weeks (Custom Import)', 'essenza-theme' ),
        'local_supplier'  => __( 'Dispatches in 2–3 weeks (Local Workshop)', 'essenza-theme' ),
        default           => __( 'In Stock &bull; Dispatches in 1–2 days', 'essenza-theme' ),
    };
}
?>

<div <?php wc_product_class( 'essenza-product-card', $product ); ?>>
    
    <!-- Thumbnail Container (1:1 Aspect Ratio) -->
    <div class="essenza-card-thumb-wrap">
        
        <!-- Sourcing Origin Badge -->
        <?php echo essenza_get_product_origin_badge( $product_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

        <a href="<?php echo esc_url( $permalink ); ?>" class="essenza-card-img-link" tabindex="-1" aria-hidden="true">
            <?php
            if ( has_post_thumbnail( $product_id ) ) {
                echo get_the_post_thumbnail( $product_id, 'woocommerce_thumbnail', [
                    'alt'   => esc_attr( $title ),
                    'class' => 'essenza-product-thumb',
                ] );
            } else {
                // Fallback elegant placeholder
                echo '<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#f5f3ef;color:#9ca3af;font-size:32px;">🛋️</div>';
            }
            ?>
        </a>

        <!-- Quick Add Action Overlay -->
        <div class="essenza-card-action-overlay">
            <?php if ( $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock() ) : ?>
                <a href="<?php echo esc_url( $product->add_to_cart_url() ); ?>"
                   data-quantity="1"
                   class="essenza-quick-add-btn button add_to_cart_button ajax_add_to_cart"
                   data-product_id="<?php echo esc_attr( $product_id ); ?>"
                   data-product_sku="<?php echo esc_attr( $product->get_sku() ); ?>"
                   aria-label="<?php echo esc_attr( $product->add_to_cart_description() ); ?>"
                   rel="nofollow">
                   <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                       <line x1="12" y1="5" x2="12" y2="19"></line>
                       <line x1="5" y1="12" x2="19" y2="12"></line>
                   </svg>
                   <span>Quick Add</span>
                </a>
            <?php else : ?>
                <a href="<?php echo esc_url( $permalink ); ?>" class="essenza-quick-add-btn">
                   <span>View Details</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Card Body -->
    <div class="essenza-card-body">
        <h3 class="essenza-card-title">
            <a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
        </h3>
        
        <div class="essenza-card-price">
            <?php echo wp_kses_post( $price_html ); ?>
        </div>

        <div class="essenza-card-lead-preview">
            <span>🚚</span>
            <span><?php echo esc_html( $lead_label ); ?></span>
        </div>
    </div>

</div>
