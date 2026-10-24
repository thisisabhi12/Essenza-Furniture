<?php
/**
 * Essenza Furniture - Demo Catalog & Environment Seed Script
 */

require_once __DIR__ . '/wp-load.php';

echo "Seeding Essenza Furniture Catalog...\n";

// 1. Ensure Shipping Zone Exists
$delivery_zones = WC_Shipping_Zones::get_zones();
$zone_id = 0;
if ( empty( $delivery_zones ) ) {
    $zone = new WC_Shipping_Zone();
    $zone->set_zone_name( 'Australia (Domestic)' );
    $zone->set_zone_order( 1 );
    $zone->add_location( 'AU', 'country' );
    $zone->save();
    $zone_id = $zone->get_id();

    // Add Flat Rate Method
    $instance_id = $zone->add_shipping_method( 'flat_rate' );
    $shipping_method = WC_Shipping_Zones::get_shipping_method( $instance_id );
    if ( $shipping_method ) {
        $shipping_method->init_instance_settings();
        $shipping_method->instance_settings['title'] = 'Australian Delivery / Courier';
        $shipping_method->instance_settings['cost']  = '45.00';
        update_option( $shipping_method->get_instance_option_key(), $shipping_method->instance_settings );
    }
    echo "Created Australia shipping zone with Flat Rate ($45.00 AUD)\n";
} else {
    echo "Shipping zones already configured.\n";
}

// 2. Enable Cash On Delivery (for local test checkouts)
update_option( 'woocommerce_cod_settings', [
    'enabled'            => 'yes',
    'title'              => 'Direct Payment / Master Order Test Gateway',
    'description'        => 'Single payment simulation for mixed-origin orders.',
    'instructions'       => 'Your order total has been billed as a unified commercial transaction.',
    'enable_for_methods' => [],
    'enable_for_virtual' => 'yes',
] );
echo "Enabled Test Payment Gateway (COD) in AUD.\n";

// 3. Create Sample Products
$demo_products = [
    [
        'title'       => 'Milano Leather Dining Chair (Set of 2)',
        'slug'        => 'milano-leather-dining-chair',
        'price'       => '480.00',
        'description' => 'Premium top-grain Italian leather dining chairs with solid walnut frame. Ready for immediate dispatch.',
        'origin'      => 'in_house',
        'lead_label'  => 'In Stock: Dispatches in 1–2 business days from Sydney warehouse',
        'min_days'    => 1,
        'max_days'    => 2,
        'tier'        => 'standard_parcel',
    ],
    [
        'title'       => 'Byron Handcrafted Oak Credenza / Sideboard',
        'slug'        => 'byron-handcrafted-oak-credenza',
        'price'       => '1850.00',
        'description' => 'Locally built sustainably sourced Tasmanian oak sideboard. Crafted to order by Australian timber artisans.',
        'origin'      => 'local_supplier',
        'lead_label'  => 'Made to Order: Dispatches in 2–3 weeks from Melbourne workshop',
        'min_days'    => 14,
        'max_days'    => 21,
        'tier'        => 'bulky_furniture',
    ],
    [
        'title'       => 'Verona Italian Bouclé Modular 4-Seater Sofa',
        'slug'        => 'verona-boucle-modular-sofa',
        'price'       => '4200.00',
        'description' => 'Sculptural luxury deep modular sofa in tactile heavy-weight cream bouclé fabric. Custom imported batch production.',
        'origin'      => 'overseas_import',
        'lead_label'  => 'Custom Import Pre-Order: Estimated 8–10 weeks (Production & Sea Container Transit)',
        'min_days'    => 56,
        'max_days'    => 70,
        'tier'        => 'white_glove',
    ],
];

foreach ( $demo_products as $pdata ) {
    $existing = get_page_by_path( $pdata['slug'], OBJECT, 'product' );
    if ( $existing ) {
        $product = wc_get_product( $existing->ID );
        echo "Updating existing product: {$pdata['title']} (ID: {$existing->ID})\n";
    } else {
        $product = new WC_Product_Simple();
        $product->set_name( $pdata['title'] );
        $product->set_slug( $pdata['slug'] );
        $product->set_status( 'publish' );
        $product->set_catalog_visibility( 'visible' );
        echo "Creating new product: {$pdata['title']}\n";
    }

    $product->set_regular_price( $pdata['price'] );
    $product->set_description( $pdata['description'] );
    $product->set_short_description( $pdata['lead_label'] );
    $product->set_manage_stock( false );
    $product->save();

    $pid = $product->get_id();
    update_post_meta( $pid, '_essenza_origin_type', $pdata['origin'] );
    update_post_meta( $pid, '_essenza_lead_time_label', $pdata['lead_label'] );
    update_post_meta( $pid, '_essenza_lead_time_min_days', $pdata['min_days'] );
    update_post_meta( $pid, '_essenza_lead_time_max_days', $pdata['max_days'] );
    update_post_meta( $pid, '_essenza_freight_tier', $pdata['tier'] );

    echo "Saved metadata for Product ID {$pid} (Origin: {$pdata['origin']})\n";
}

echo "Demo Catalog seeded successfully!\n";
