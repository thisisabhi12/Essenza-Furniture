<?php
/**
 * Plugin Name: My First Code
 * Description: Tested from Antigravity IDE.
 * Version: 1.0.0
 * Author: Abhishek
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access block
}

// Ye function website ke bottom par ek badge show karega
add_action( 'wp_footer', function() {
    echo '<div style="position:fixed;bottom:20px;right:20px;background:#1e293b;color:#38bdf8;padding:12px 20px;border-radius:10px;font-family:sans-serif;box-shadow:0 4px 12px rgba(0,0,0,0.15);z-index:99999;">
            🚀 Running live from Antigravity IDE
          </div>';
});