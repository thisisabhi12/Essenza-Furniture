<?php
require_once __DIR__ . '/wp-load.php';

$living_term = wp_insert_term( 'Living', 'product_cat', [ 'slug' => 'living' ] );
$dining_term = wp_insert_term( 'Dining', 'product_cat', [ 'slug' => 'dining' ] );

$living_id = is_wp_error( $living_term ) ? $living_term->error_data['term_exists'] : $living_term['term_id'];
$dining_id = is_wp_error( $dining_term ) ? $dining_term->error_data['term_exists'] : $dining_term['term_id'];

echo "Living Cat ID: {$living_id}\n";
echo "Dining Cat ID: {$dining_id}\n";

// Map products
$living_products = [ 14, 17, 19, 20 ]; // Verona Sofa, Roma Armchair, Florence Coffee Table, Tivoli Ottoman
$dining_products = [ 12, 13, 18, 21 ]; // Milano Chairs, Byron Credenza, Sorento Dining Table, Bellagio Console

foreach ( $living_products as $pid ) {
    wp_set_object_terms( $pid, (int)$living_id, 'product_cat', true );
    echo "Assigned #{$pid} to Living\n";
}

foreach ( $dining_products as $pid ) {
    wp_set_object_terms( $pid, (int)$dining_id, 'product_cat', true );
    echo "Assigned #{$pid} to Dining\n";
}

echo "Category assignment complete!\n";
