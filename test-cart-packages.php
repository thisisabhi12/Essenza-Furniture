<?php
require_once __DIR__ . '/wp-load.php';

// Add 3 items to cart
WC()->cart->empty_cart();
WC()->cart->add_to_cart( 12, 1 ); // Warehouse
WC()->cart->add_to_cart( 13, 1 ); // Local
WC()->cart->add_to_cart( 14, 1 ); // Import

echo "Cart item count: " . WC()->cart->get_cart_contents_count() . "\n";
echo "Cart Total: " . WC()->cart->get_cart_total() . "\n";

$packages = WC()->shipping()->calculate_shipping_for_package( [], 0 );
$packages = Essenza_Fulfillment_Core::split_packages_by_origin( [] );

echo "Packages generated: " . count( $packages ) . "\n";
foreach ( $packages as $i => $pkg ) {
    echo "  Package " . ($i + 1) . ": " . Essenza_Fulfillment_Core::format_package_names( 'Pkg', $i, $pkg ) . "\n";
}
