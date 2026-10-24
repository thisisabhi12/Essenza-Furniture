<?php
require_once __DIR__ . '/wp-load.php';

$order = wc_get_order( 15 );
if ( ! $order ) {
    echo "Order 15 not found\n";
    exit;
}

echo "Order ID: " . $order->get_id() . "\n";
echo "Master Status: " . $order->get_status() . "\n";
echo "Total Paid: " . $order->get_formatted_order_total() . "\n";
echo "Shipments meta:\n";
print_r( $order->get_meta( '_essenza_shipments' ) );

echo "\nLine items & their stored meta:\n";
foreach ( $order->get_items() as $item_id => $item ) {
    echo "Item #{$item_id}: {$item->get_name()}\n";
    echo "  Origin: " . $item->get_meta( '_fulfillment_origin' ) . "\n";
    echo "  Dispatch Origin: " . $item->get_meta( 'Dispatch Origin' ) . "\n";
    echo "  Lead Time Notice: " . $item->get_meta( 'Lead Time Notice' ) . "\n";
}
