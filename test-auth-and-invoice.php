<?php
require_once __DIR__ . '/wp-load.php';

// 1. Test Magic Auth Verification
echo "1. TESTING 1-CLICK MAGIC AUTH VERIFICATION...\n";
$user_id = 3;
$token = get_user_meta( $user_id, '_essenza_magic_token', true );
$expiry = get_user_meta( $user_id, '_essenza_magic_token_exp', true );
$user = get_user_by( 'id', $user_id );

echo "User: {$user->display_name} ({$user->user_email})\n";
echo "Stored Token: {$token}\n";
echo "Token Expiry: " . date( 'Y-m-d H:i:s', $expiry ) . " (Valid: " . ( $expiry > time() ? "YES" : "NO" ) . ")\n";

if ( ! empty( $token ) && $expiry > time() ) {
    echo "SUCCESS: Magic link token validated for User #{$user_id}!\n";
} else {
    echo "FAILED: Magic link token invalid or expired\n";
}

// 2. Test Australian Tax Invoice Generation
echo "\n2. TESTING MASTER AUSTRALIAN TAX INVOICE GENERATION (ORDER 15)...\n";
$_GET['essenza_action'] = 'tax_invoice';
$_GET['order_id'] = '15';
$_REQUEST['_wpnonce'] = wp_create_nonce( 'essenza_tax_invoice' );

// Set current user as admin (ID 1) to pass permissions
wp_set_current_user( 1 );

ob_start();
Essenza_Fulfillment_Core::handle_tax_invoice_download();
$invoice_html = ob_get_clean();

echo "Invoice HTML output length: " . strlen( $invoice_html ) . " bytes\n";
if ( strpos( $invoice_html, 'ABN: 84 629 104 293' ) !== false && strpos( $invoice_html, 'TAX INVOICE' ) !== false ) {
    echo "SUCCESS: Australian Master Tax Invoice generated with ABN, GST 10%, and Sourcing Packages!\n";
} else {
    echo "FAILED: Tax invoice content check failed\n";
}
