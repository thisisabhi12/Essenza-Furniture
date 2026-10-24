<?php
require_once __DIR__ . '/wp-load.php';

$query = new WP_Query( [
    'post_type'      => 'product',
    'posts_per_page' => 15,
    'orderby'        => 'ID',
    'order'          => 'DESC',
] );

echo "LIVE WOOCOMMERCE CATALOG INVENTORY:\n";
echo str_repeat( '=', 85 ) . "\n";
printf( "%-4s | %-36s | %-12s | %-10s | %-12s\n", "ID", "Product Title", "Origin", "Price", "Has Image?" );
echo str_repeat( '-', 85 ) . "\n";

while ( $query->have_posts() ) {
    $query->the_post();
    $pid    = get_the_ID();
    $origin = get_post_meta( $pid, '_essenza_origin_type', true ) ?: 'in_house';
    $price  = get_post_meta( $pid, '_price', true );
    $has_img= has_post_thumbnail( $pid ) ? 'YES (ID: ' . get_post_thumbnail_id( $pid ) . ')' : 'NO';

    printf( "%-4d | %-36s | %-12s | $%-9.2f | %-12s\n",
        $pid,
        substr( get_the_title(), 0, 36 ),
        $origin,
        floatval( $price ),
        $has_img
    );
    echo "     URL: " . get_permalink() . "\n";
}
echo str_repeat( '=', 85 ) . "\n";
