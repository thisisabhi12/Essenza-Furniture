<?php
require_once __DIR__ . '/wp-load.php';

$order = wc_get_order( 15 );
ob_start();
do_action( 'woocommerce_order_details_after_order_table', $order );
$output = ob_get_clean();

echo "Length of tracker HTML: " . strlen( $output ) . " bytes\n";
if ( strpos( $output, 'Multi-Stage Delivery & Tracking Status' ) !== false ) {
    echo "SUCCESS: Found Multi-Stage Delivery Tracker in customer order view!\n";
    echo "Sample Output Snippet:\n";
    echo substr( strip_tags( $output ), 0, 450 ) . "...\n";
} else {
    echo "FAILED: Tracker not found\n";
}
