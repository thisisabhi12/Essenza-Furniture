<?php
require_once __DIR__ . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$products = [
    17 => [ 'url' => 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?auto=format&fit=crop&w=800&q=80', 'file' => 'roma-armchair.jpg' ],
    18 => [ 'url' => 'https://images.unsplash.com/photo-1615066390971-03e4e1c36ddf?auto=format&fit=crop&w=800&q=80', 'file' => 'sorento-table.jpg' ],
    19 => [ 'url' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=800&q=80', 'file' => 'florence-table.jpg' ],
    20 => [ 'url' => 'https://images.unsplash.com/photo-1598300042247-d088f8ab3a91?auto=format&fit=crop&w=800&q=80', 'file' => 'tivoli-ottoman.jpg' ],
    21 => [ 'url' => 'https://images.unsplash.com/photo-1618221195710-dd6b41faaea6?auto=format&fit=crop&w=800&q=80', 'file' => 'bellagio-console.jpg' ],
    12 => [ 'url' => 'https://images.unsplash.com/photo-1503602642458-232111445657?auto=format&fit=crop&w=800&q=80', 'file' => 'milano-chairs.jpg' ],
    13 => [ 'url' => 'https://images.unsplash.com/photo-1538688525198-9b88f6f53126?auto=format&fit=crop&w=800&q=80', 'file' => 'byron-credenza.jpg' ],
    14 => [ 'url' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=800&q=80', 'file' => 'verona-sofa.jpg' ],
];

echo "Downloading & sideloading furniture imagery...\n";

foreach ( $products as $pid => $data ) {
    $product = wc_get_product( $pid );
    if ( ! $product ) continue;

    if ( ! has_post_thumbnail( $pid ) ) {
        echo "Fetching image for #{$pid} ({$product->get_name()})...\n";
        $tmp = download_url( $data['url'] );
        if ( is_wp_error( $tmp ) ) {
            echo "  -> download_url failed: " . $tmp->get_error_message() . "\n";
            continue;
        }

        $file_array = [
            'name'     => $data['file'],
            'tmp_name' => $tmp,
        ];

        $id = media_handle_sideload( $file_array, $pid, $product->get_name() );
        if ( is_wp_error( $id ) ) {
            @unlink( $tmp );
            echo "  -> media_handle_sideload failed: " . $id->get_error_message() . "\n";
        } else {
            set_post_thumbnail( $pid, $id );
            echo "  -> SUCCESS: Attached media ID {$id} as featured thumbnail for #{$pid}\n";
        }
    } else {
        echo "Product #{$pid} already has featured thumbnail.\n";
    }
}

echo "Image sideloading complete!\n";
