<?php
/**
 * Template Name: Essenza Admin Suite Dashboard
 * Description: High-end bespoke dynamic admin dashboard for managing Essenza Furniture catalog, image uploads, supply chain attributes, and inventory.
 *
 * @package Essenza_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_authorized = Essenza_Admin_Dashboard::is_admin_authorized();
$login_error   = get_transient( 'essenza_login_error' );
if ( $login_error ) {
    delete_transient( 'essenza_login_error' );
}

// Fetch categories for filter and editor
$categories = get_terms( [
    'taxonomy'   => 'product_cat',
    'hide_empty' => false,
] );

// Fetch all products for initial render
$products_query = new WP_Query( [
    'post_type'      => 'product',
    'posts_per_page' => -1,
    'post_status'    => [ 'publish', 'draft' ],
    'orderby'        => 'ID',
    'order'          => 'DESC',
] );

$total_products   = $products_query->found_posts;
$published_count  = 0;
$draft_count      = 0;
$in_stock_count   = 0;
$out_stock_count  = 0;
$total_valuation  = 0;
$origin_counts    = [
    'in_house'       => 0,
    'local_supplier' => 0,
    'overseas_import'=> 0,
];

$product_rows = [];

while ( $products_query->have_posts() ) {
    $products_query->the_post();
    $pid      = get_the_ID();
    $p_obj    = wc_get_product( $pid );
    $status   = get_post_status( $pid );
    $origin   = get_post_meta( $pid, '_essenza_origin_type', true ) ?: 'in_house';
    $price    = floatval( $p_obj ? $p_obj->get_price() : 0 );
    $stock_st = $p_obj ? $p_obj->get_stock_status() : 'instock';

    if ( 'publish' === $status ) {
        $published_count++;
    } else {
        $draft_count++;
    }

    if ( 'instock' === $stock_st ) {
        $in_stock_count++;
    } else {
        $out_stock_count++;
    }

    $total_valuation += $price;

    if ( isset( $origin_counts[ $origin ] ) ) {
        $origin_counts[ $origin ]++;
    }

    $product_rows[] = Essenza_Admin_Dashboard::get_product_table_payload( $pid );
}
wp_reset_postdata();

$current_user = wp_get_current_user();
$nonce        = wp_create_nonce( 'essenza_admin_nonce' );
$logout_nonce = wp_create_nonce( 'essenza_admin_logout' );
?><!DOCTYPE html>
<html lang="en-AU">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_authorized ? 'Inventory & Catalog Suite — Essenza Admin' : 'Admin Login — Essenza Suite'; ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-body: #090D0B;
            --bg-surface: #111714;
            --bg-card: #161F1A;
            --bg-card-hover: #1D2822;
            --bg-input: #0D1310;
            --border: #233029;
            --border-focus: #C5A880;
            --gold: #C5A880;
            --gold-light: #E8D7C3;
            --gold-dark: #9E7F55;
            --text-main: #FFFFFF;
            --text-muted: #8E9B93;
            --text-subtle: #5C6962;
            --green: #10B981;
            --green-bg: rgba(16, 185, 129, 0.12);
            --blue: #38BDF8;
            --blue-bg: rgba(56, 189, 248, 0.12);
            --amber: #F59E0B;
            --amber-bg: rgba(245, 158, 11, 0.12);
            --red: #EF4444;
            --red-bg: rgba(239, 68, 68, 0.12);
            --shadow-lg: 0 20px 40px -15px rgba(0, 0, 0, 0.7);
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 14px;
            --font-serif: 'Cormorant Garamond', Georgia, serif;
            --font-sans: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: var(--font-sans);
            font-size: 14px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
        }

        a {
            color: var(--gold);
            text-decoration: none;
            transition: color 0.2s ease;
        }
        a:hover {
            color: var(--gold-light);
        }

        button {
            font-family: inherit;
            cursor: pointer;
            border: none;
            background: none;
            outline: none;
            transition: all 0.2s ease;
        }

        /* -------------------------------------------------------------------------
           1. AUTHENTICATION / LOGIN VIEW
           ------------------------------------------------------------------------- */
        .auth-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 24px;
            background: radial-gradient(circle at 50% 20%, rgba(197, 168, 128, 0.08), transparent 70%), var(--bg-body);
        }

        .auth-card {
            width: 100%;
            max-width: 440px;
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 44px 36px;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .auth-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--gold-dark), var(--gold-light), var(--gold-dark));
        }

        .auth-brand {
            text-align: center;
            margin-bottom: 32px;
        }
        .auth-brand h1 {
            font-family: var(--font-serif);
            font-size: 32px;
            letter-spacing: 0.15em;
            color: var(--gold);
            margin-bottom: 4px;
            font-weight: 600;
        }
        .auth-brand .sub {
            font-size: 11px;
            letter-spacing: 0.25em;
            text-transform: uppercase;
            color: var(--text-muted);
        }

        .auth-alert {
            padding: 12px 16px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .auth-alert.error {
            background: var(--red-bg);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #FCA5A5;
        }
        .auth-alert.success {
            background: var(--green-bg);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #6EE7B7;
        }

        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 8px;
        }
        .form-control {
            width: 100%;
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 12px 14px;
            color: var(--text-main);
            font-size: 14px;
            transition: all 0.2s ease;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(197, 168, 128, 0.15);
        }

        .btn-gold {
            width: 100%;
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: #0A0E0C;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 14px;
            border-radius: var(--radius-sm);
            box-shadow: 0 4px 15px rgba(197, 168, 128, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-gold:hover {
            background: linear-gradient(135deg, var(--gold-light), var(--gold));
            box-shadow: 0 6px 20px rgba(197, 168, 128, 0.35);
            transform: translateY(-1px);
        }

        .auth-footer {
            margin-top: 24px;
            text-align: center;
            font-size: 12px;
            color: var(--text-subtle);
        }

        /* -------------------------------------------------------------------------
           2. MAIN DASHBOARD VIEW
           ------------------------------------------------------------------------- */
        .dash-layout {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Top Navigation Header */
        .dash-nav {
            background: var(--bg-surface);
            border-bottom: 1px solid var(--border);
            padding: 0 32px;
            height: 72px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .nav-brand .brand-logo {
            font-family: var(--font-serif);
            font-size: 26px;
            font-weight: 700;
            letter-spacing: 0.12em;
            color: var(--gold);
        }
        .nav-brand .badge-suite {
            background: rgba(197, 168, 128, 0.12);
            border: 1px solid rgba(197, 168, 128, 0.25);
            color: var(--gold-light);
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 20px;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .btn-link-store {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--text-muted);
            font-size: 13px;
            padding: 8px 14px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            background: var(--bg-card);
        }
        .btn-link-store:hover {
            color: var(--text-main);
            border-color: var(--border-focus);
        }

        .user-pill {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 14px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 30px;
            font-size: 13px;
        }
        .user-avatar {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: var(--gold-dark);
            color: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 12px;
        }

        .btn-logout {
            color: var(--text-muted);
            font-size: 13px;
            padding: 8px 14px;
            border-radius: var(--radius-sm);
            border: 1px solid transparent;
        }
        .btn-logout:hover {
            color: var(--red);
            background: var(--red-bg);
            border-color: rgba(239, 68, 68, 0.2);
        }

        /* Dashboard Body Content */
        .dash-content {
            padding: 32px;
            max-width: 1440px;
            margin: 0 auto;
            width: 100%;
            flex: 1;
        }

        /* 3. Metrics Cards Grid */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .metric-card {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 24px;
            position: relative;
            overflow: hidden;
            transition: transform 0.2s ease, border-color 0.2s ease;
        }
        .metric-card:hover {
            transform: translateY(-2px);
            border-color: var(--border-focus);
        }
        .metric-card .icon {
            font-size: 24px;
            margin-bottom: 12px;
        }
        .metric-card .label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            margin-bottom: 6px;
        }
        .metric-card .val {
            font-size: 28px;
            font-family: var(--font-serif);
            font-weight: 700;
            color: var(--text-main);
        }
        .metric-card .sub {
            font-size: 12px;
            color: var(--text-subtle);
            margin-top: 6px;
        }

        /* 4. Controls & Filter Toolbar */
        .toolbar {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 18px 24px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .toolbar-filters {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            flex: 1;
        }

        .search-box {
            position: relative;
            min-width: 260px;
        }
        .search-box input {
            width: 100%;
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 10px 14px 10px 36px;
            color: var(--text-main);
            font-size: 13px;
        }
        .search-box input:focus {
            outline: none;
            border-color: var(--border-focus);
        }
        .search-box .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-subtle);
            pointer-events: none;
        }

        .filter-select {
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 10px 14px;
            color: var(--text-main);
            font-size: 13px;
            cursor: pointer;
        }
        .filter-select:focus {
            outline: none;
            border-color: var(--border-focus);
        }

        .btn-add-product {
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: #0A0E0C;
            font-weight: 600;
            font-size: 13px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            padding: 11px 20px;
            border-radius: var(--radius-sm);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(197, 168, 128, 0.2);
            white-space: nowrap;
        }
        .btn-add-product:hover {
            background: linear-gradient(135deg, var(--gold-light), var(--gold));
            box-shadow: 0 6px 18px rgba(197, 168, 128, 0.35);
            transform: translateY(-1px);
        }

        /* 5. Product Table */
        .table-wrap {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
        }

        .product-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .product-table th {
            background: rgba(0, 0, 0, 0.25);
            padding: 16px 20px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border);
            font-weight: 600;
        }

        .product-table td {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
            color: var(--text-main);
        }

        .product-table tr:hover td {
            background: var(--bg-card);
        }

        /* Product Cell */
        .product-cell {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .thumb-preview {
            width: 52px;
            height: 52px;
            border-radius: var(--radius-sm);
            background: var(--bg-card);
            border: 1px solid var(--border);
            overflow: hidden;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .thumb-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .product-info .title {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-main);
            margin-bottom: 4px;
        }
        .product-info .meta-line {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: var(--text-muted);
        }

        /* Status & Origin Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 12px;
            letter-spacing: 0.04em;
        }
        .badge-status-publish {
            background: var(--green-bg);
            color: var(--green);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .badge-status-draft {
            background: var(--amber-bg);
            color: var(--amber);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .badge-stock-in {
            background: var(--green-bg);
            color: var(--green);
        }
        .badge-stock-out {
            background: var(--red-bg);
            color: var(--red);
        }

        .badge-origin-warehouse {
            background: var(--green-bg);
            color: #34D399;
            border: 1px solid rgba(16, 185, 129, 0.25);
        }
        .badge-origin-local {
            background: var(--blue-bg);
            color: #38BDF8;
            border: 1px solid rgba(56, 189, 248, 0.25);
        }
        .badge-origin-import {
            background: var(--amber-bg);
            color: #FBBF24;
            border: 1px solid rgba(245, 158, 11, 0.25);
        }

        .featured-toggle {
            font-size: 18px;
            color: var(--text-subtle);
            cursor: pointer;
            padding: 4px;
            transition: color 0.2s ease, transform 0.2s ease;
        }
        .featured-toggle.active {
            color: var(--gold);
        }
        .featured-toggle:hover {
            transform: scale(1.15);
        }

        .actions-cell {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-action-edit {
            background: var(--bg-card);
            border: 1px solid var(--border);
            color: var(--text-main);
            padding: 6px 12px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 500;
        }
        .btn-action-edit:hover {
            border-color: var(--border-focus);
            color: var(--gold);
        }

        .btn-action-delete {
            background: var(--bg-card);
            border: 1px solid var(--border);
            color: var(--text-muted);
            padding: 6px 10px;
            border-radius: var(--radius-sm);
            font-size: 12px;
        }
        .btn-action-delete:hover {
            background: var(--red-bg);
            color: var(--red);
            border-color: rgba(239, 68, 68, 0.3);
        }

        .table-empty {
            text-align: center;
            padding: 48px !important;
            color: var(--text-muted);
        }

        /* -------------------------------------------------------------------------
           6. SLIDE-OVER PRODUCT EDIT/CREATE MODAL
           ------------------------------------------------------------------------- */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(4px);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: flex-end;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .modal-backdrop.open {
            display: flex;
            opacity: 1;
        }

        .modal-drawer {
            width: 100%;
            max-width: 720px;
            height: 100vh;
            background: var(--bg-surface);
            border-left: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            box-shadow: -10px 0 30px rgba(0, 0, 0, 0.6);
            transform: translateX(100%);
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            overflow: hidden;
        }
        .modal-backdrop.open .modal-drawer {
            transform: translateX(0);
        }

        .modal-header {
            padding: 24px 32px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(0, 0, 0, 0.2);
        }
        .modal-header h2 {
            font-family: var(--font-serif);
            font-size: 24px;
            color: var(--gold);
            font-weight: 600;
        }
        .modal-close {
            color: var(--text-muted);
            font-size: 24px;
            line-height: 1;
            padding: 6px;
        }
        .modal-close:hover {
            color: var(--text-main);
        }

        .modal-body {
            padding: 32px;
            overflow-y: auto;
            flex: 1;
        }

        .modal-footer {
            padding: 20px 32px;
            border-top: 1px solid var(--border);
            background: rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
        }

        /* Form Layout inside Drawer */
        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .section-divider {
            margin: 28px 0 20px 0;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--gold);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Image Upload Box */
        .image-upload-zone {
            background: var(--bg-input);
            border: 2px dashed var(--border);
            border-radius: var(--radius-md);
            padding: 24px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }
        .image-upload-zone:hover, .image-upload-zone.dragover {
            border-color: var(--gold);
            background: rgba(197, 168, 128, 0.05);
        }
        .image-upload-zone input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .preview-container {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-top: 16px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 12px;
        }
        .preview-img {
            width: 72px;
            height: 72px;
            object-fit: cover;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
        }
        .preview-meta {
            flex: 1;
            font-size: 12px;
            color: var(--text-muted);
        }
        .preview-meta strong {
            display: block;
            color: var(--text-main);
            margin-bottom: 4px;
        }
        .btn-remove-img {
            color: var(--red);
            font-size: 12px;
            padding: 6px 10px;
            border-radius: var(--radius-sm);
            background: var(--red-bg);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        /* Category Checkbox Grid */
        .cat-checkboxes {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            max-height: 140px;
            overflow-y: auto;
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 12px;
        }
        .cat-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--text-main);
            cursor: pointer;
        }

        /* -------------------------------------------------------------------------
           7. CONFIRMATION MODAL & TOAST NOTIFICATIONS
           ------------------------------------------------------------------------- */
        .confirm-dialog {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            max-width: 440px;
            width: 90%;
            padding: 32px;
            box-shadow: var(--shadow-lg);
            text-align: center;
        }
        .confirm-dialog .icon {
            font-size: 42px;
            margin-bottom: 16px;
        }
        .confirm-dialog h3 {
            font-size: 18px;
            font-family: var(--font-serif);
            color: var(--text-main);
            margin-bottom: 8px;
        }
        .confirm-dialog p {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 24px;
        }
        .confirm-actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .toast-container {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 2000;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
        }
        .toast {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 14px 20px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            color: var(--text-main);
            pointer-events: auto;
            transform: translateX(100%);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .toast.show {
            transform: translateX(0);
            opacity: 1;
        }
        .toast.success {
            border-left: 4px solid var(--green);
        }
        .toast.error {
            border-left: 4px solid var(--red);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dash-nav {
                padding: 0 16px;
            }
            .dash-content {
                padding: 16px;
            }
            .form-grid-2 {
                grid-template-columns: 1fr;
            }
            .modal-drawer {
                max-width: 100%;
            }
            .toolbar {
                flex-direction: column;
                align-items: stretch;
            }
            .toolbar-filters {
                flex-direction: column;
                align-items: stretch;
            }
            .product-table th:nth-child(2),
            .product-table td:nth-child(2),
            .product-table th:nth-child(4),
            .product-table td:nth-child(4) {
                display: none;
            }
        }
    </style>
</head>
<body>

<?php if ( ! $is_authorized ) : ?>
    <!-- =========================================================================
         1. SECURE ADMIN LOGIN SCREEN
         ========================================================================= -->
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-brand">
                <h1>ESSENZA</h1>
                <div class="sub">Architectural Inventory Suite</div>
            </div>

            <?php if ( ! empty( $login_error ) ) : ?>
                <div class="auth-alert error">
                    <span>⚠️</span>
                    <span><?php echo esc_html( $login_error ); ?></span>
                </div>
            <?php elseif ( isset( $_GET['logged_out'] ) ) : ?>
                <div class="auth-alert success">
                    <span>✓</span>
                    <span>Session terminated securely.</span>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?php echo esc_url( home_url( '/' . Essenza_Admin_Dashboard::PAGE_SLUG . '/' ) ); ?>">
                <?php wp_nonce_field( 'essenza_admin_login', 'essenza_login_nonce' ); ?>
                <input type="hidden" name="essenza_admin_login_action" value="1">

                <div class="form-group">
                    <label class="form-label" for="username">Administrator Username / Email</label>
                    <input type="text" id="username" name="username" class="form-control" placeholder="admin or email" required autofocus autocomplete="username">
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="••••••••••••" required autocomplete="current-password">
                </div>

                <div class="form-group" style="display:flex;align-items:center;justify-content:space-between;">
                    <label style="display:flex;align-items:center;gap:8px;font-size:12px;color:var(--text-muted);cursor:pointer;">
                        <input type="checkbox" name="remember" value="1">
                        <span>Keep session active</span>
                    </label>
                    <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" style="font-size:12px;">Forgot password?</a>
                </div>

                <button type="submit" class="btn-gold" style="margin-top:12px;">
                    <span>Access Management Suite</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </button>
            </form>

            <div class="auth-footer">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>">&larr; Return to Essenza Public Store</a>
            </div>
        </div>
    </div>

<?php else : ?>
    <!-- =========================================================================
         2. AUTHENTICATED ADMIN INVENTORY SUITE
         ========================================================================= -->
    <div class="dash-layout">
        
        <!-- Navigation Header -->
        <header class="dash-nav">
            <div class="nav-brand">
                <span class="brand-logo">ESSENZA</span>
                <span class="badge-suite">Inventory Suite</span>
            </div>

            <div class="nav-actions">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" class="btn-link-store" title="Open storefront in new tab">
                    <span>View Storefront</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                        <polyline points="15 3 21 3 21 9"></polyline>
                        <line x1="10" y1="14" x2="21" y2="3"></line>
                    </svg>
                </a>

                <div class="user-pill">
                    <span class="user-avatar"><?php echo esc_html( strtoupper( substr( $current_user->display_name, 0, 1 ) ) ); ?></span>
                    <span><?php echo esc_html( $current_user->display_name ); ?> (Admin)</span>
                </div>

                <a href="<?php echo esc_url( wp_nonce_url( home_url( '/' . Essenza_Admin_Dashboard::PAGE_SLUG . '/?action=essenza_logout' ), 'essenza_admin_logout' ) ); ?>" class="btn-logout" title="End administrative session">
                    Logout
                </a>
            </div>
        </header>

        <!-- Main Workspace -->
        <main class="dash-content">
            
            <!-- Metrics Summary Cards -->
            <section class="metrics-grid">
                <div class="metric-card">
                    <div class="icon">🛋️</div>
                    <div class="label">Total Catalog Products</div>
                    <div class="val" id="metric-total"><?php echo esc_html( $total_products ); ?></div>
                    <div class="sub"><?php echo esc_html( $published_count ); ?> Live &bull; <?php echo esc_html( $draft_count ); ?> Drafts</div>
                </div>

                <div class="metric-card">
                    <div class="icon">🟢</div>
                    <div class="label">Sydney Warehouse Stock</div>
                    <div class="val" id="metric-warehouse"><?php echo esc_html( $origin_counts['in_house'] ); ?></div>
                    <div class="sub">1–2 Business Days Fast Dispatch</div>
                </div>

                <div class="metric-card">
                    <div class="icon">🔵</div>
                    <div class="label">Local Australian Makers</div>
                    <div class="val" id="metric-local"><?php echo esc_html( $origin_counts['local_supplier'] ); ?></div>
                    <div class="sub">Handcrafted Made-to-Order Pieces</div>
                </div>

                <div class="metric-card">
                    <div class="icon">🚢</div>
                    <div class="label">Custom Sea Freight Imports</div>
                    <div class="val" id="metric-import"><?php echo esc_html( $origin_counts['overseas_import'] ); ?></div>
                    <div class="sub">International Direct Sourcing</div>
                </div>
            </section>

            <!-- Action & Filter Toolbar -->
            <div class="toolbar">
                <div class="toolbar-filters">
                    <!-- Search -->
                    <div class="search-box">
                        <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <input type="text" id="filter-search" placeholder="Search by product name, SKU...">
                    </div>

                    <!-- Category Filter -->
                    <select id="filter-category" class="filter-select">
                        <option value="">All Categories</option>
                        <?php foreach ( $categories as $cat ) : ?>
                            <option value="<?php echo esc_attr( strtolower( $cat->name ) ); ?>"><?php echo esc_html( $cat->name ); ?> (<?php echo esc_html( $cat->count ); ?>)</option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Origin Filter -->
                    <select id="filter-origin" class="filter-select">
                        <option value="">All Supply Origins</option>
                        <option value="in_house">In-House Warehouse</option>
                        <option value="local_supplier">Local Workshop</option>
                        <option value="overseas_import">Custom Import</option>
                    </select>

                    <!-- Status Filter -->
                    <select id="filter-status" class="filter-select">
                        <option value="">All Statuses</option>
                        <option value="publish">Published</option>
                        <option value="draft">Drafts</option>
                    </select>
                </div>

                <!-- Add Product Button -->
                <button type="button" class="btn-add-product" id="btn-open-create">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    <span>New Furniture Piece</span>
                </button>
            </div>

            <!-- Product Inventory Table -->
            <div class="table-wrap">
                <table class="product-table" id="product-table">
                    <thead>
                        <tr>
                            <th style="width: 320px;">Product Name & SKU</th>
                            <th>Category</th>
                            <th>Supply Origin</th>
                            <th>Price (AUD)</th>
                            <th>Stock</th>
                            <th>Visibility</th>
                            <th style="text-align:center;">Featured</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="product-rows-body">
                        <?php if ( ! empty( $product_rows ) ) : ?>
                            <?php foreach ( $product_rows as $row ) : ?>
                                <tr data-id="<?php echo esc_attr( $row['id'] ); ?>"
                                    data-title="<?php echo esc_attr( strtolower( $row['title'] ) ); ?>"
                                    data-sku="<?php echo esc_attr( strtolower( $row['sku'] ) ); ?>"
                                    data-category="<?php echo esc_attr( strtolower( $row['categories'] ) ); ?>"
                                    data-origin="<?php echo esc_attr( $row['origin'] ); ?>"
                                    data-status="<?php echo esc_attr( $row['status'] ); ?>">
                                    
                                    <!-- Title & Thumb -->
                                    <td>
                                        <div class="product-cell">
                                            <div class="thumb-preview">
                                                <?php if ( ! empty( $row['image_url'] ) ) : ?>
                                                    <img src="<?php echo esc_url( $row['image_url'] ); ?>" alt="<?php echo esc_attr( $row['title'] ); ?>">
                                                <?php else : ?>
                                                    <span style="font-size:20px;">🛋️</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="product-info">
                                                <div class="title"><?php echo esc_html( $row['title'] ); ?></div>
                                                <div class="meta-line">
                                                    <span>SKU: <?php echo esc_html( $row['sku'] ); ?></span>
                                                    <span>&bull;</span>
                                                    <a href="<?php echo esc_url( $row['permalink'] ); ?>" target="_blank" style="font-size:11px;color:var(--gold);">View live ↗</a>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Category -->
                                    <td>
                                        <span style="color:var(--text-muted);font-size:13px;"><?php echo esc_html( $row['categories'] ); ?></span>
                                    </td>

                                    <!-- Origin -->
                                    <td>
                                        <?php echo $row['origin_badge']; // phpcs:ignore ?>
                                    </td>

                                    <!-- Price -->
                                    <td>
                                        <div style="font-weight:600;font-size:14px;color:var(--text-main);">
                                            <?php echo wp_kses_post( $row['price_html'] ); ?>
                                        </div>
                                    </td>

                                    <!-- Stock -->
                                    <td>
                                        <span class="badge <?php echo 'instock' === $row['stock_status'] ? 'badge-stock-in' : 'badge-stock-out'; ?>">
                                            <?php echo 'instock' === $row['stock_status'] ? '✓ In Stock' : '✕ Out of Stock'; ?>
                                        </span>
                                    </td>

                                    <!-- Visibility Toggle -->
                                    <td>
                                        <button type="button" class="badge btn-toggle-visibility <?php echo 'publish' === $row['status'] ? 'badge-status-publish' : 'badge-status-draft'; ?>" data-id="<?php echo esc_attr( $row['id'] ); ?>" title="Click to toggle publish/draft">
                                            <?php echo 'publish' === $row['status'] ? 'Published' : 'Draft'; ?>
                                        </button>
                                    </td>

                                    <!-- Featured Toggle -->
                                    <td style="text-align:center;">
                                        <button type="button" class="featured-toggle <?php echo ! empty( $row['is_featured'] ) ? 'active' : ''; ?>" data-id="<?php echo esc_attr( $row['id'] ); ?>" title="Toggle Featured on Homepage">
                                            ★
                                        </button>
                                    </td>

                                    <!-- Actions -->
                                    <td style="text-align:right;">
                                        <div class="actions-cell" style="justify-content: flex-end;">
                                            <button type="button" class="btn-action-edit btn-edit-product" data-id="<?php echo esc_attr( $row['id'] ); ?>">
                                                Edit
                                            </button>
                                            <button type="button" class="btn-action-delete btn-delete-product" data-id="<?php echo esc_attr( $row['id'] ); ?>" data-name="<?php echo esc_attr( $row['title'] ); ?>">
                                                Delete
                                            </button>
                                        </div>
                                    </td>

                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="8" class="table-empty">No products found in the catalog.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </main>
    </div>

    <!-- =========================================================================
         3. CREATE & EDIT PRODUCT SLIDE-OUT DRAWER
         ========================================================================= -->
    <div class="modal-backdrop" id="product-modal">
        <div class="modal-drawer">
            <div class="modal-header">
                <h2 id="modal-heading">Add New Furniture Piece</h2>
                <button type="button" class="modal-close" id="btn-close-modal">&times;</button>
            </div>

            <form id="product-form" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="product_id" id="field-product-id" value="0">
                    <input type="hidden" name="remove_image" id="field-remove-image" value="0">

                    <!-- Section: General Details -->
                    <div class="section-divider" style="margin-top:0;">
                        <span>1. Product Identity</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="field-title">Product Name *</label>
                        <input type="text" id="field-title" name="title" class="form-control" placeholder="e.g. Verona Italian Bouclé Modular Sofa" required>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label" for="field-slug">URL Slug (Optional)</label>
                            <input type="text" id="field-slug" name="slug" class="form-control" placeholder="auto-generated-if-blank">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="field-sku">SKU Code</label>
                            <input type="text" id="field-sku" name="sku" class="form-control" placeholder="e.g. ESS-VRN-01">
                        </div>
                    </div>

                    <!-- Categories -->
                    <div class="form-group">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                            <label class="form-label" style="margin-bottom:0;">Assigned Categories *</label>
                            <button type="button" id="btn-prompt-cat" style="font-size:12px;color:var(--gold);">+ Add New Category</button>
                        </div>
                        <div class="cat-checkboxes" id="cat-checkboxes-container">
                            <?php foreach ( $categories as $cat ) : ?>
                                <label class="cat-item">
                                    <input type="checkbox" name="categories[]" value="<?php echo esc_attr( $cat->term_id ); ?>">
                                    <span><?php echo esc_html( $cat->name ); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Section: Image Management -->
                    <div class="section-divider">
                        <span>2. Photography & Imagery</span>
                    </div>

                    <div class="image-upload-zone" id="drop-zone">
                        <input type="file" id="field-image" name="product_image" accept="image/jpeg,image/png,image/webp">
                        <div style="font-size:32px;margin-bottom:8px;">📷</div>
                        <div style="font-weight:600;font-size:14px;color:var(--gold-light);margin-bottom:4px;">
                            Drag & Drop product photography here or browse
                        </div>
                        <div style="font-size:12px;color:var(--text-muted);">
                            Supports JPG, PNG, WebP up to 8MB. Recommended square 1:1 ratio (min 1000x1000px).
                        </div>
                    </div>

                    <!-- Current / Selected Image Preview -->
                    <div class="preview-container" id="image-preview-wrap" style="display:none;">
                        <img src="" id="image-preview-img" class="preview-img" alt="Preview">
                        <div class="preview-meta">
                            <strong id="preview-filename">Selected Image</strong>
                            <span id="preview-filesize">Image attached</span>
                        </div>
                        <button type="button" class="btn-remove-img" id="btn-remove-img">Remove Image</button>
                    </div>

                    <!-- Section: Commercial Pricing & Inventory -->
                    <div class="section-divider">
                        <span>3. Commercial Pricing & Stock</span>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label" for="field-reg-price">Regular Price (AUD $) *</label>
                            <input type="number" step="0.01" min="0" id="field-reg-price" name="regular_price" class="form-control" placeholder="e.g. 2800.00" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="field-sale-price">Sale Price (AUD $, Optional)</label>
                            <input type="number" step="0.01" min="0" id="field-sale-price" name="sale_price" class="form-control" placeholder="e.g. 2450.00">
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label" for="field-stock-status">Stock Status</label>
                            <select id="field-stock-status" name="stock_status" class="form-control">
                                <option value="instock">In Stock</option>
                                <option value="outofstock">Out of Stock</option>
                                <option value="onbackorder">On Backorder / Pre-Order</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="field-stock-qty">Stock Quantity (Optional)</label>
                            <input type="number" min="0" id="field-stock-qty" name="stock_quantity" class="form-control" placeholder="Unlimited if blank">
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label" for="field-status">Catalog Visibility</label>
                            <select id="field-status" name="status" class="form-control">
                                <option value="publish">Published (Visible on site)</option>
                                <option value="draft">Draft (Hidden from public)</option>
                            </select>
                        </div>
                        <div class="form-group" style="display:flex;align-items:flex-end;">
                            <label style="display:flex;align-items:center;gap:10px;padding:12px 0;cursor:pointer;">
                                <input type="checkbox" id="field-featured" name="is_featured" value="1">
                                <span style="font-weight:600;color:var(--gold);">★ Feature on Homepage</span>
                            </label>
                        </div>
                    </div>

                    <!-- Section: Multi-Origin Supply Chain -->
                    <div class="section-divider">
                        <span>4. Essenza Supply Chain Engine</span>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label" for="field-origin">Sourcing Origin *</label>
                            <select id="field-origin" name="origin_type" class="form-control">
                                <option value="in_house">In-House Sydney Warehouse (Fast Dispatch)</option>
                                <option value="local_supplier">Local Australian Workshop (Made to Order)</option>
                                <option value="overseas_import">Custom Import - Sea Freight (Long Lead)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="field-lead-label">Lead Time Banner Text</label>
                            <input type="text" id="field-lead-label" name="lead_time_label" class="form-control" placeholder="e.g. In Stock &bull; Dispatches in 24–48 hours">
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label" for="field-lead-min">Min Lead Time (Days)</label>
                            <input type="number" min="0" id="field-lead-min" name="lead_time_min_days" class="form-control" value="1">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="field-lead-max">Max Lead Time (Days)</label>
                            <input type="number" min="0" id="field-lead-max" name="lead_time_max_days" class="form-control" value="3">
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label" for="field-freight-tier">Freight Tier</label>
                            <select id="field-freight-tier" name="freight_tier" class="form-control">
                                <option value="standard_parcel">Standard Parcel Courier</option>
                                <option value="bulky_furniture" selected>Bulky Furniture / Tail-Lift</option>
                                <option value="oversized_pallet">Oversized Pallet / Direct Depot</option>
                                <option value="fragile_two_man">White Glove Two-Person Delivery</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="field-cubic">Cubic Volume (m³)</label>
                            <input type="number" step="0.01" min="0" id="field-cubic" name="cubic_meters" class="form-control" placeholder="0.55">
                        </div>
                    </div>

                    <!-- Section: Editorial Descriptions -->
                    <div class="section-divider">
                        <span>5. Editorial Descriptions</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="field-short-desc">Short Summary / Highlights</label>
                        <textarea id="field-short-desc" name="short_description" class="form-control" rows="2" placeholder="Brief summary of materials and dimensions..."></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="field-desc">Full Architectural Description</label>
                        <textarea id="field-desc" name="description" class="form-control" rows="5" placeholder="Detailed architectural narrative, joinery techniques, upholstery specifications..."></textarea>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-link-store" id="btn-cancel-modal">Cancel</button>
                    <button type="submit" class="btn-gold" id="btn-save-product" style="width:auto;padding:12px 28px;">
                        <span id="save-btn-text">Save & Publish Piece</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- =========================================================================
         4. DESTRUCTIVE ACTION CONFIRMATION DIALOG
         ========================================================================= -->
    <div class="modal-backdrop" id="delete-modal" style="justify-content:center;">
        <div class="confirm-dialog">
            <div class="icon">🗑️</div>
            <h3>Permanently Delete Furniture Piece?</h3>
            <p>Are you sure you want to delete <strong id="delete-product-title" style="color:var(--text-main);">this product</strong>? This action directly removes the item from the WooCommerce database and cannot be undone.</p>
            <div class="confirm-actions">
                <button type="button" class="btn-link-store" id="btn-cancel-delete">Cancel</button>
                <button type="button" class="btn-remove-img" id="btn-confirm-delete" style="padding:10px 20px;font-size:13px;font-weight:600;">
                    Yes, Delete Piece
                </button>
            </div>
        </div>
    </div>

    <!-- =========================================================================
         5. TOAST NOTIFICATION CONTAINER
         ========================================================================= -->
    <div class="toast-container" id="toast-container"></div>

    <!-- =========================================================================
         6. DASHBOARD CLIENT JAVASCRIPT CONTROLLER
         ========================================================================= -->
    <script>
    (function() {
        const AJAX_URL = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
        const NONCE    = '<?php echo esc_js( $nonce ); ?>';

        // Elements
        const modalBackdrop    = document.getElementById('product-modal');
        const modalHeading     = document.getElementById('modal-heading');
        const productForm      = document.getElementById('product-form');
        const btnOpenCreate    = document.getElementById('btn-open-create');
        const btnCloseModal    = document.getElementById('btn-close-modal');
        const btnCancelModal   = document.getElementById('btn-cancel-modal');
        const dropZone         = document.getElementById('drop-zone');
        const fileInput        = document.getElementById('field-image');
        const imagePreviewWrap = document.getElementById('image-preview-wrap');
        const imagePreviewImg  = document.getElementById('image-preview-img');
        const previewFilename  = document.getElementById('preview-filename');
        const previewFilesize  = document.getElementById('preview-filesize');
        const btnRemoveImg     = document.getElementById('btn-remove-img');
        const fieldRemoveImg   = document.getElementById('field-remove-image');
        const saveBtnText      = document.getElementById('save-btn-text');
        const deleteModal      = document.getElementById('delete-modal');
        const deleteProdTitle  = document.getElementById('delete-product-title');
        const btnConfirmDelete = document.getElementById('btn-confirm-delete');
        const btnCancelDelete  = document.getElementById('btn-cancel-delete');
        const toastContainer   = document.getElementById('toast-container');
        const btnPromptCat     = document.getElementById('btn-prompt-cat');
        const catContainer     = document.getElementById('cat-checkboxes-container');

        let pendingDeleteId = 0;

        // Toast Helper
        function showToast(msg, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            const icon = type === 'success' ? '✓' : '⚠️';
            toast.innerHTML = `<span style="font-weight:700;">${icon}</span><span>${msg}</span>`;
            toastContainer.appendChild(toast);
            
            requestAnimationFrame(() => {
                toast.classList.add('show');
            });

            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 350);
            }, 3500);
        }

        // Open Modal (Create Mode)
        btnOpenCreate.addEventListener('click', () => {
            productForm.reset();
            document.getElementById('field-product-id').value = '0';
            fieldRemoveImg.value = '0';
            modalHeading.textContent = 'Add New Furniture Piece';
            saveBtnText.textContent = 'Save & Publish Piece';
            imagePreviewWrap.style.display = 'none';
            imagePreviewImg.src = '';
            
            // Uncheck categories
            document.querySelectorAll('input[name="categories[]"]').forEach(cb => cb.checked = false);
            
            modalBackdrop.classList.add('open');
        });

        // Close Modal
        function closeModal() {
            modalBackdrop.classList.remove('open');
        }
        btnCloseModal.addEventListener('click', closeModal);
        btnCancelModal.addEventListener('click', closeModal);
        modalBackdrop.addEventListener('click', (e) => {
            if (e.target === modalBackdrop) closeModal();
        });

        // Image Drag & Drop and Preview
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });
        dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                handleFileSelect(fileInput.files[0]);
            }
        });
        fileInput.addEventListener('change', () => {
            if (fileInput.files && fileInput.files[0]) {
                handleFileSelect(fileInput.files[0]);
            }
        });

        function handleFileSelect(file) {
            if (!file.type.match('image.*')) {
                showToast('Please select a valid image (JPG, PNG, WebP).', 'error');
                return;
            }
            fieldRemoveImg.value = '0';
            previewFilename.textContent = file.name;
            previewFilesize.textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';

            const reader = new FileReader();
            reader.onload = (e) => {
                imagePreviewImg.src = e.target.result;
                imagePreviewWrap.style.display = 'flex';
            };
            reader.readAsDataURL(file);
        }

        btnRemoveImg.addEventListener('click', () => {
            fileInput.value = '';
            imagePreviewImg.src = '';
            imagePreviewWrap.style.display = 'none';
            fieldRemoveImg.value = '1';
        });

        // Save / Submit Product Form
        productForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('btn-save-product');
            btn.disabled = true;
            const originalText = saveBtnText.textContent;
            saveBtnText.textContent = 'Saving to Database...';

            const formData = new FormData(productForm);
            formData.append('action', 'essenza_admin_save_product');
            formData.append('security', NONCE);

            fetch(AJAX_URL, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                saveBtnText.textContent = originalText;

                if (data.success) {
                    showToast(data.data.message || 'Product saved successfully!');
                    closeModal();
                    updateOrAddTableRow(data.data.product);
                } else {
                    showToast(data.data.message || 'Failed to save product.', 'error');
                }
            })
            .catch(err => {
                btn.disabled = false;
                saveBtnText.textContent = originalText;
                showToast('Network error while saving product.', 'error');
            });
        });

        // Edit Product Button Click
        document.addEventListener('click', function(e) {
            const editBtn = e.target.closest('.btn-edit-product');
            if (!editBtn) return;

            const pid = editBtn.dataset.id;
            modalHeading.textContent = `Edit Furniture Piece #${pid}`;
            saveBtnText.textContent = 'Update & Sync Piece';

            showToast('Loading product details...', 'success');

            fetch(`${AJAX_URL}?action=essenza_admin_get_product_data&security=${NONCE}&product_id=${pid}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    showToast(data.data.message || 'Error fetching product', 'error');
                    return;
                }
                const p = data.data;
                document.getElementById('field-product-id').value = p.id;
                document.getElementById('field-title').value = p.title || '';
                document.getElementById('field-slug').value = p.slug || '';
                document.getElementById('field-sku').value = p.sku || '';
                document.getElementById('field-reg-price').value = p.regular_price || '';
                document.getElementById('field-sale-price').value = p.sale_price || '';
                document.getElementById('field-stock-status').value = p.stock_status || 'instock';
                document.getElementById('field-stock-qty').value = p.stock_quantity || '';
                document.getElementById('field-status').value = p.status || 'publish';
                document.getElementById('field-featured').checked = !!p.is_featured;
                document.getElementById('field-origin').value = p.origin_type || 'in_house';
                document.getElementById('field-lead-label').value = p.lead_time_label || '';
                document.getElementById('field-lead-min').value = p.lead_time_min_days || 1;
                document.getElementById('field-lead-max').value = p.lead_time_max_days || 3;
                document.getElementById('field-freight-tier').value = p.freight_tier || 'bulky_furniture';
                document.getElementById('field-cubic').value = p.cubic_meters || 0.0;
                document.getElementById('field-short-desc').value = p.short_description || '';
                document.getElementById('field-desc').value = p.description || '';
                
                // Categories
                document.querySelectorAll('input[name="categories[]"]').forEach(cb => {
                    cb.checked = p.categories && p.categories.includes(parseInt(cb.value));
                });

                // Image
                fieldRemoveImg.value = '0';
                fileInput.value = '';
                if (p.image_url) {
                    imagePreviewImg.src = p.image_url;
                    previewFilename.textContent = 'Existing Product Photography';
                    previewFilesize.textContent = 'Active Featured Image';
                    imagePreviewWrap.style.display = 'flex';
                } else {
                    imagePreviewWrap.style.display = 'none';
                }

                modalBackdrop.classList.add('open');
            })
            .catch(() => showToast('Error connecting to server.', 'error'));
        });

        // Quick Toggle Visibility (Publish / Draft)
        document.addEventListener('click', function(e) {
            const toggleBtn = e.target.closest('.btn-toggle-visibility');
            if (!toggleBtn) return;

            const pid = toggleBtn.dataset.id;
            toggleBtn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'essenza_admin_toggle_visibility');
            formData.append('security', NONCE);
            formData.append('product_id', pid);

            fetch(AJAX_URL, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                toggleBtn.disabled = false;
                if (data.success) {
                    toggleBtn.textContent = data.data.label;
                    if (data.data.new_status === 'publish') {
                        toggleBtn.className = 'badge btn-toggle-visibility badge-status-publish';
                    } else {
                        toggleBtn.className = 'badge btn-toggle-visibility badge-status-draft';
                    }
                    const row = document.querySelector(`tr[data-id="${pid}"]`);
                    if (row) row.dataset.status = data.data.new_status;
                    showToast(data.data.message);
                } else {
                    showToast(data.data.message || 'Error updating status', 'error');
                }
            })
            .catch(() => {
                toggleBtn.disabled = false;
                showToast('Error connecting to server.', 'error');
            });
        });

        // Quick Toggle Featured
        document.addEventListener('click', function(e) {
            const featBtn = e.target.closest('.featured-toggle');
            if (!featBtn) return;

            const pid = featBtn.dataset.id;
            featBtn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'essenza_admin_toggle_featured');
            formData.append('security', NONCE);
            formData.append('product_id', pid);

            fetch(AJAX_URL, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                featBtn.disabled = false;
                if (data.success) {
                    if (data.data.is_featured) {
                        featBtn.classList.add('active');
                    } else {
                        featBtn.classList.remove('active');
                    }
                    showToast(data.data.message);
                }
            })
            .catch(() => {
                featBtn.disabled = false;
            });
        });

        // Delete Product Handling
        document.addEventListener('click', function(e) {
            const delBtn = e.target.closest('.btn-delete-product');
            if (!delBtn) return;

            pendingDeleteId = delBtn.dataset.id;
            deleteProdTitle.textContent = `"${delBtn.dataset.name}"`;
            deleteModal.classList.add('open');
        });

        btnCancelDelete.addEventListener('click', () => {
            deleteModal.classList.remove('open');
            pendingDeleteId = 0;
        });

        btnConfirmDelete.addEventListener('click', () => {
            if (!pendingDeleteId) return;

            btnConfirmDelete.disabled = true;
            btnConfirmDelete.textContent = 'Deleting...';

            const formData = new FormData();
            formData.append('action', 'essenza_admin_delete_product');
            formData.append('security', NONCE);
            formData.append('product_id', pendingDeleteId);

            fetch(AJAX_URL, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                btnConfirmDelete.disabled = false;
                btnConfirmDelete.textContent = 'Yes, Delete Piece';
                deleteModal.classList.remove('open');

                if (data.success) {
                    const row = document.querySelector(`tr[data-id="${pendingDeleteId}"]`);
                    if (row) {
                        row.style.transition = 'all 0.3s ease';
                        row.style.opacity = '0';
                        row.style.transform = 'scale(0.95)';
                        setTimeout(() => row.remove(), 300);
                    }
                    showToast(data.data.message);
                    pendingDeleteId = 0;
                } else {
                    showToast(data.data.message || 'Error deleting product', 'error');
                }
            })
            .catch(() => {
                btnConfirmDelete.disabled = false;
                btnConfirmDelete.textContent = 'Yes, Delete Piece';
                showToast('Error connecting to server.', 'error');
            });
        });

        // Add Category on the fly
        btnPromptCat.addEventListener('click', () => {
            const catName = prompt('Enter new furniture category name:');
            if (!catName || !catName.trim()) return;

            const formData = new FormData();
            formData.append('action', 'essenza_admin_add_category');
            formData.append('security', NONCE);
            formData.append('cat_name', catName.trim());

            fetch(AJAX_URL, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const label = document.createElement('label');
                    label.className = 'cat-item';
                    label.innerHTML = `
                        <input type="checkbox" name="categories[]" value="${data.data.term_id}" checked>
                        <span>${data.data.name}</span>
                    `;
                    catContainer.appendChild(label);
                    showToast(data.data.message);
                } else {
                    showToast(data.data.message || 'Error creating category', 'error');
                }
            });
        });

        // Real-time Search and Filtering
        const filterSearch   = document.getElementById('filter-search');
        const filterCategory = document.getElementById('filter-category');
        const filterOrigin   = document.getElementById('filter-origin');
        const filterStatus   = document.getElementById('filter-status');

        function applyFilters() {
            const q = filterSearch.value.trim().toLowerCase();
            const cat = filterCategory.value.toLowerCase();
            const origin = filterOrigin.value.toLowerCase();
            const status = filterStatus.value.toLowerCase();

            const rows = document.querySelectorAll('#product-rows-body tr[data-id]');
            let visibleCount = 0;

            rows.forEach(row => {
                const rTitle = row.dataset.title || '';
                const rSku   = row.dataset.sku || '';
                const rCat   = row.dataset.category || '';
                const rOrig  = row.dataset.origin || '';
                const rStat  = row.dataset.status || '';

                const matchesSearch = !q || rTitle.includes(q) || rSku.includes(q);
                const matchesCat    = !cat || rCat.includes(cat);
                const matchesOrigin = !origin || rOrig === origin;
                const matchesStatus = !status || rStat === status;

                if (matchesSearch && matchesCat && matchesOrigin && matchesStatus) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
        }

        filterSearch.addEventListener('input', applyFilters);
        filterCategory.addEventListener('change', applyFilters);
        filterOrigin.addEventListener('change', applyFilters);
        filterStatus.addEventListener('change', applyFilters);

        // Helper to insert or update table row dynamically
        function updateOrAddTableRow(p) {
            if (!p) return;

            let row = document.querySelector(`tr[data-id="${p.id}"]`);
            const isNew = !row;

            if (isNew) {
                row = document.createElement('tr');
                row.dataset.id = p.id;
                document.getElementById('product-rows-body').prepend(row);
                
                // Update total count metric
                const totalMetric = document.getElementById('metric-total');
                if (totalMetric) totalMetric.textContent = parseInt(totalMetric.textContent || 0) + 1;
            }

            row.dataset.title = (p.title || '').toLowerCase();
            row.dataset.sku   = (p.sku || '').toLowerCase();
            row.dataset.category = (p.categories || '').toLowerCase();
            row.dataset.origin = p.origin;
            row.dataset.status = p.status;

            const isInstock = p.stock_status === 'instock';
            const isPublish = p.status === 'publish';

            row.innerHTML = `
                <td>
                    <div class="product-cell">
                        <div class="thumb-preview">
                            ${p.image_url ? `<img src="${p.image_url}" alt="${p.title}">` : '<span style="font-size:20px;">🛋️</span>'}
                        </div>
                        <div class="product-info">
                            <div class="title">${p.title}</div>
                            <div class="meta-line">
                                <span>SKU: ${p.sku}</span>
                                <span>&bull;</span>
                                <a href="${p.permalink}" target="_blank" style="font-size:11px;color:var(--gold);">View live ↗</a>
                            </div>
                        </div>
                    </div>
                </td>
                <td>
                    <span style="color:var(--text-muted);font-size:13px;">${p.categories}</span>
                </td>
                <td>
                    ${p.origin_badge || ''}
                </td>
                <td>
                    <div style="font-weight:600;font-size:14px;color:var(--text-main);">
                        ${p.price_html}
                    </div>
                </td>
                <td>
                    <span class="badge ${isInstock ? 'badge-stock-in' : 'badge-stock-out'}">
                        ${isInstock ? '✓ In Stock' : '✕ Out of Stock'}
                    </span>
                </td>
                <td>
                    <button type="button" class="badge btn-toggle-visibility ${isPublish ? 'badge-status-publish' : 'badge-status-draft'}" data-id="${p.id}" title="Click to toggle publish/draft">
                        ${isPublish ? 'Published' : 'Draft'}
                    </button>
                </td>
                <td style="text-align:center;">
                    <button type="button" class="featured-toggle ${p.is_featured ? 'active' : ''}" data-id="${p.id}" title="Toggle Featured on Homepage">
                        ★
                    </button>
                </td>
                <td style="text-align:right;">
                    <div class="actions-cell" style="justify-content: flex-end;">
                        <button type="button" class="btn-action-edit btn-edit-product" data-id="${p.id}">
                            Edit
                        </button>
                        <button type="button" class="btn-action-delete btn-delete-product" data-id="${p.id}" data-name="${p.title}">
                            Delete
                        </button>
                    </div>
                </td>
            `;

            row.style.background = 'rgba(197, 168, 128, 0.15)';
            setTimeout(() => {
                row.style.transition = 'background 1s ease';
                row.style.background = '';
            }, 1000);
        }

    })();
    </script>
<?php endif; ?>
</body>
</html>
