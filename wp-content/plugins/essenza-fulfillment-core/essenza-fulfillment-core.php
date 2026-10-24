<?php
/**
 * Plugin Name: Essenza Furniture - Multi-Origin Fulfillment Core
 * Plugin URI: https://www.essenzafurniture.com.au
 * Description: Enterprise fulfillment engine for Essenza Furniture: Multi-origin product schema, dynamic cart package splitting, line-item tracking, and custom order lifecycles.
 * Version: 1.0.0
 * Author: Senior Solutions Architect & Lead Developer
 * Text Domain: essenza-fulfillment
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Essenza_Fulfillment_Core {

    const ORIGIN_WAREHOUSE = 'in_house';
    const ORIGIN_LOCAL     = 'local_supplier';
    const ORIGIN_IMPORT    = 'overseas_import';

    public static function init() {
        // 1. Product Schema & Admin UI
        add_action( 'woocommerce_product_options_general_product_data', [ __CLASS__, 'render_product_admin_fields' ] );
        add_action( 'woocommerce_process_product_meta', [ __CLASS__, 'save_product_admin_fields' ] );

        // 2. Frontend Customer Transparency (PDP, Cart, Checkout)
        add_action( 'woocommerce_single_product_summary', [ __CLASS__, 'render_pdp_lead_time_notice' ], 25 );
        add_filter( 'woocommerce_get_item_data', [ __CLASS__, 'display_lead_time_in_cart' ], 10, 2 );

        // 3. Multi-Origin Package Splitting Engine
        add_filter( 'woocommerce_cart_shipping_packages', [ __CLASS__, 'split_packages_by_origin' ] );
        add_filter( 'woocommerce_shipping_package_name', [ __CLASS__, 'format_package_names' ], 10, 3 );

        // 4. Line-Item Order Meta & Badges
        add_action( 'woocommerce_checkout_create_order_line_item', [ __CLASS__, 'save_line_item_origin_meta' ], 10, 4 );
        add_action( 'woocommerce_after_order_itemmeta', [ __CLASS__, 'render_admin_order_item_badge' ], 10, 3 );

        // 5. Custom Order Statuses
        add_action( 'init', [ __CLASS__, 'register_custom_order_statuses' ] );
        add_filter( 'wc_order_statuses', [ __CLASS__, 'add_custom_order_statuses_to_select' ] );

        // 6. Line-Item Shipment Tracking Meta Box in Admin
        add_action( 'add_meta_boxes', [ __CLASS__, 'add_shipment_manager_meta_box' ] );
        add_action( 'woocommerce_process_shop_order_meta', [ __CLASS__, 'save_shipment_manager_data' ] );

        // 7. Customer Front-End Order Tracking Milestone Card
        add_action( 'woocommerce_order_details_after_order_table', [ __CLASS__, 'render_customer_order_shipments_tracker' ] );

        // 8. Passwordless Magic Link Authentication Bridge
        add_action( 'init', [ __CLASS__, 'handle_magic_auth_login' ] );
        add_action( 'woocommerce_login_form_end', [ __CLASS__, 'render_magic_link_request_ui' ] );
        add_action( 'template_redirect', [ __CLASS__, 'process_magic_link_request' ] );

        // 9. Master Australian Tax Invoice (GST 10%) Generator
        add_action( 'template_redirect', [ __CLASS__, 'handle_tax_invoice_download' ] );
        add_filter( 'woocommerce_my_account_my_orders_actions', [ __CLASS__, 'add_tax_invoice_order_action' ], 10, 2 );
        add_action( 'woocommerce_order_details_after_order_table', [ __CLASS__, 'render_invoice_download_button' ], 20 );

        // 10. Auto-configure Default Flat Rate Shipping & Test Gateway on Activation
        register_activation_hook( __FILE__, [ __CLASS__, 'on_activation' ] );
    }

    /**
     * Sourcing Origin Labels
     */
    public static function get_origin_labels() {
        return [
            self::ORIGIN_WAREHOUSE => __( 'In-House Warehouse (Fast Dispatch)', 'essenza-fulfillment' ),
            self::ORIGIN_LOCAL     => __( 'Local Australian Supplier (Procured on Demand)', 'essenza-fulfillment' ),
            self::ORIGIN_IMPORT    => __( 'Overseas Import - China (Sea Freight)', 'essenza-fulfillment' ),
        ];
    }

    /**
     * Render Product Schema Fields in Admin
     */
    public static function render_product_admin_fields() {
        echo '<div class="options_group essenza-sourcing-panel" style="background:#f8fafc;padding:12px;border-left:4px solid #0284c7;margin:10px 0;">';
        echo '<h4 style="margin:0 0 10px 0;color:#0f172a;font-weight:600;">🛋️ ' . esc_html__( 'Essenza Sourcing & Fulfillment Engine', 'essenza-fulfillment' ) . '</h4>';

        woocommerce_wp_select( [
            'id'      => '_essenza_origin_type',
            'label'   => __( 'Fulfillment Origin', 'essenza-fulfillment' ),
            'options' => self::get_origin_labels(),
        ] );

        woocommerce_wp_text_input( [
            'id'          => '_essenza_lead_time_label',
            'label'       => __( 'Lead Time Notice', 'essenza-fulfillment' ),
            'placeholder' => __( 'e.g. In Stock: 1-3 Business Days or Estimated 8-10 Weeks', 'essenza-fulfillment' ),
            'desc_tip'    => true,
            'description' => __( 'Custom message displayed on product page and checkout.', 'essenza-fulfillment' ),
        ] );

        woocommerce_wp_text_input( [
            'id'          => '_essenza_lead_time_min_days',
            'label'       => __( 'Min Lead Days', 'essenza-fulfillment' ),
            'type'        => 'number',
            'custom_attributes' => [ 'step' => '1', 'min' => '0' ],
        ] );

        woocommerce_wp_text_input( [
            'id'          => '_essenza_lead_time_max_days',
            'label'       => __( 'Max Lead Days', 'essenza-fulfillment' ),
            'type'        => 'number',
            'custom_attributes' => [ 'step' => '1', 'min' => '0' ],
        ] );

        woocommerce_wp_select( [
            'id'      => '_essenza_freight_tier',
            'label'   => __( 'Freight / Logistics Tier', 'essenza-fulfillment' ),
            'options' => [
                'standard_parcel'  => __( 'Standard Carton / Courier (AusPost / StarTrack)', 'essenza-fulfillment' ),
                'bulky_furniture'  => __( 'Bulky Freight / Tail-Lift (Allied / Hunter Express)', 'essenza-fulfillment' ),
                'white_glove'      => __( 'Fragile / Two-Person White Glove Transport', 'essenza-fulfillment' ),
            ],
        ] );

        echo '</div>';
    }

    /**
     * Save Product Schema Meta
     */
    public static function save_product_admin_fields( $post_id ) {
        $keys = [
            '_essenza_origin_type',
            '_essenza_lead_time_label',
            '_essenza_lead_time_min_days',
            '_essenza_lead_time_max_days',
            '_essenza_freight_tier',
        ];

        foreach ( $keys as $key ) {
            if ( isset( $_POST[ $key ] ) ) {
                update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
            }
        }
    }

    /**
     * Render PDP Lead Time Notice Badge
     */
    public static function render_pdp_lead_time_notice() {
        global $product;
        if ( ! $product ) return;

        $product_id = $product->get_id();
        $origin     = get_post_meta( $product_id, '_essenza_origin_type', true ) ?: self::ORIGIN_WAREHOUSE;
        $label      = get_post_meta( $product_id, '_essenza_lead_time_label', true );

        if ( empty( $label ) ) {
            $label = match ( $origin ) {
                self::ORIGIN_IMPORT => __( 'Custom Pre-Order: Estimated 8–12 weeks production & sea transit', 'essenza-fulfillment' ),
                self::ORIGIN_LOCAL  => __( 'Special Order: Dispatches in 1–3 weeks from Australian workshop', 'essenza-fulfillment' ),
                default             => __( 'In Stock: Dispatches in 1–3 business days from Sydney warehouse', 'essenza-fulfillment' ),
            };
        }

        $badge_style = match ( $origin ) {
            self::ORIGIN_IMPORT => 'background:#fffbeb;color:#92400e;border:1px solid #fde68a;',
            self::ORIGIN_LOCAL  => 'background:#f0f9ff;color:#0369a1;border:1px solid #bae6fd;',
            default             => 'background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;',
        };

        $badge_icon = match ( $origin ) {
            self::ORIGIN_IMPORT => '🚢',
            self::ORIGIN_LOCAL  => '🏭',
            default             => '⚡',
        };

        echo '<div class="essenza-delivery-badge" style="margin:16px 0;padding:12px 16px;border-radius:8px;' . esc_attr( $badge_style ) . 'font-size:14px;display:flex;align-items:center;gap:12px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">';
        echo '<span style="font-size:22px;line-height:1;">' . esc_html( $badge_icon ) . '</span>';
        echo '<div>';
        echo '<strong style="display:block;margin-bottom:2px;">' . esc_html__( 'Fulfillment Timeline', 'essenza-fulfillment' ) . '</strong>';
        echo '<span style="font-weight:500;">' . esc_html( $label ) . '</span>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Show Lead Time inside Cart & Checkout item meta
     */
    public static function display_lead_time_in_cart( $item_data, $cart_item ) {
        $product_id = $cart_item['product_id'];
        $origin     = get_post_meta( $product_id, '_essenza_origin_type', true ) ?: self::ORIGIN_WAREHOUSE;
        $label      = get_post_meta( $product_id, '_essenza_lead_time_label', true );

        if ( empty( $label ) ) {
            $label = match ( $origin ) {
                self::ORIGIN_IMPORT => __( 'Estimated 8–12 weeks (Overseas Import)', 'essenza-fulfillment' ),
                self::ORIGIN_LOCAL  => __( 'Estimated 1–3 weeks (Local Workshop)', 'essenza-fulfillment' ),
                default             => __( 'Dispatches in 1–3 business days', 'essenza-fulfillment' ),
            };
        }

        $origin_name = match ( $origin ) {
            self::ORIGIN_IMPORT => __( 'Overseas Import (China)', 'essenza-fulfillment' ),
            self::ORIGIN_LOCAL  => __( 'Local Australian Supplier', 'essenza-fulfillment' ),
            default             => __( 'In-House Warehouse', 'essenza-fulfillment' ),
        };

        $item_data[] = [
            'key'   => __( 'Dispatch Origin', 'essenza-fulfillment' ),
            'value' => $origin_name,
        ];

        $item_data[] = [
            'key'   => __( 'Estimated Timeline', 'essenza-fulfillment' ),
            'value' => $label,
        ];

        return $item_data;
    }

    /**
     * Core Multi-Origin Package Splitting Filter Hook
     */
    public static function split_packages_by_origin( $packages ) {
        if ( ! WC()->cart || WC()->cart->is_empty() ) {
            return $packages;
        }

        $split_items = [
            self::ORIGIN_WAREHOUSE => [],
            self::ORIGIN_LOCAL     => [],
            self::ORIGIN_IMPORT    => [],
        ];

        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            $product_id = $cart_item['product_id'];
            $origin     = get_post_meta( $product_id, '_essenza_origin_type', true ) ?: self::ORIGIN_WAREHOUSE;

            if ( ! isset( $split_items[ $origin ] ) ) {
                $origin = self::ORIGIN_WAREHOUSE;
            }

            $split_items[ $origin ][ $cart_item_key ] = $cart_item;
        }

        $new_packages = [];
        $destination  = [
            'country'   => WC()->customer ? WC()->customer->get_shipping_country() : 'AU',
            'state'     => WC()->customer ? WC()->customer->get_shipping_state() : 'NSW',
            'postcode'  => WC()->customer ? WC()->customer->get_shipping_postcode() : '2000',
            'city'      => WC()->customer ? WC()->customer->get_shipping_city() : 'Sydney',
            'address'   => WC()->customer ? WC()->customer->get_shipping_address() : '',
            'address_2' => WC()->customer ? WC()->customer->get_shipping_address_2() : '',
        ];

        foreach ( $split_items as $origin_key => $items ) {
            if ( ! empty( $items ) ) {
                $new_packages[] = [
                    'contents'        => $items,
                    'contents_cost'   => array_sum( wp_list_pluck( $items, 'line_total' ) ),
                    'applied_coupons' => WC()->cart->get_applied_coupons(),
                    'user'            => [ 'ID' => get_current_user_id() ],
                    'destination'     => $destination,
                    'origin_key'      => $origin_key,
                ];
            }
        }

        return ! empty( $new_packages ) ? $new_packages : $packages;
    }

    /**
     * Custom Package Names Rendered at Checkout
     */
    public static function format_package_names( $name, $i, $package ) {
        if ( isset( $package['origin_key'] ) ) {
            return match ( $package['origin_key'] ) {
                self::ORIGIN_IMPORT => __( 'Shipment: Custom Import (Sea Freight, 8–12 wks)', 'essenza-fulfillment' ),
                self::ORIGIN_LOCAL  => __( 'Shipment: Local Australian Workshop (1–3 wks)', 'essenza-fulfillment' ),
                default             => __( 'Shipment: In-House Warehouse (Fast Dispatch, 1–3 days)', 'essenza-fulfillment' ),
            };
        }
        return $name;
    }

    /**
     * Save Line-Item Origin into Order Metadata
     */
    public static function save_line_item_origin_meta( $item, $cart_item_key, $values, $order ) {
        $product_id = $values['product_id'];
        $origin     = get_post_meta( $product_id, '_essenza_origin_type', true ) ?: self::ORIGIN_WAREHOUSE;
        $label      = get_post_meta( $product_id, '_essenza_lead_time_label', true );

        $origin_title = match ( $origin ) {
            self::ORIGIN_IMPORT => 'Overseas Import (China)',
            self::ORIGIN_LOCAL  => 'Local Australian Supplier',
            default             => 'In-House Warehouse',
        };

        $item->add_meta_data( '_fulfillment_origin', $origin, true );
        $item->add_meta_data( 'Dispatch Origin', $origin_title, true );
        if ( ! empty( $label ) ) {
            $item->add_meta_data( 'Lead Time Notice', $label, true );
        }
    }

    /**
     * Render Badges in WooCommerce Admin Order Details
     */
    public static function render_admin_order_item_badge( $item_id, $item, $product ) {
        if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
            return;
        }

        $origin = $item->get_meta( '_fulfillment_origin' );
        if ( empty( $origin ) && $product ) {
            $origin = get_post_meta( $product->get_id(), '_essenza_origin_type', true );
        }
        $origin = $origin ?: self::ORIGIN_WAREHOUSE;

        $badge_style = match ( $origin ) {
            self::ORIGIN_IMPORT => 'background:#fef3c7;color:#92400e;border:1px solid #fde68a;',
            self::ORIGIN_LOCAL  => 'background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;',
            default             => 'background:#dcfce7;color:#166534;border:1px solid #bbf7d0;',
        };

        $origin_name = match ( $origin ) {
            self::ORIGIN_IMPORT => '🟡 Overseas Import (Sea Freight)',
            self::ORIGIN_LOCAL  => '🔵 Local Supplier (On-Demand)',
            default             => '🟢 In-House Warehouse',
        };

        echo '<div style="margin-top:6px;display:inline-block;padding:3px 8px;border-radius:4px;font-size:11px;font-weight:600;' . esc_attr( $badge_style ) . '">';
        echo esc_html( $origin_name );
        echo '</div>';
    }

    /**
     * Register Custom Order Statuses
     */
    public static function register_custom_order_statuses() {
        register_post_status( 'wc-partially-shipped', [
            'label'                     => _x( 'Partially Shipped', 'Order status', 'essenza-fulfillment' ),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop( 'Partially Shipped <span class="count">(%s)</span>', 'Partially Shipped <span class="count">(%s)</span>', 'essenza-fulfillment' ),
        ] );

        register_post_status( 'wc-supplier-ordered', [
            'label'                     => _x( 'Supplier Ordered', 'Order status', 'essenza-fulfillment' ),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop( 'Supplier Ordered <span class="count">(%s)</span>', 'Supplier Ordered <span class="count">(%s)</span>', 'essenza-fulfillment' ),
        ] );

        register_post_status( 'wc-container-transit', [
            'label'                     => _x( 'Container In Transit', 'Order status', 'essenza-fulfillment' ),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop( 'Container In Transit <span class="count">(%s)</span>', 'Container In Transit <span class="count">(%s)</span>', 'essenza-fulfillment' ),
        ] );
    }

    /**
     * Add Statuses to WooCommerce Status Dropdown
     */
    public static function add_custom_order_statuses_to_select( $order_statuses ) {
        $new_statuses = [];
        foreach ( $order_statuses as $key => $status ) {
            $new_statuses[ $key ] = $status;
            if ( 'wc-processing' === $key ) {
                $new_statuses['wc-supplier-ordered']   = _x( 'Supplier Ordered', 'Order status', 'essenza-fulfillment' );
                $new_statuses['wc-container-transit']  = _x( 'Container In Transit', 'Order status', 'essenza-fulfillment' );
                $new_statuses['wc-partially-shipped']  = _x( 'Partially Shipped', 'Order status', 'essenza-fulfillment' );
            }
        }
        return $new_statuses;
    }

    /**
     * Line-Item Shipment Manager Meta Box
     */
    public static function add_shipment_manager_meta_box() {
        $screen = class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) && wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id( 'shop-order' )
            : 'shop_order';

        add_meta_box(
            'essenza_line_item_shipments',
            __( '📦 Essenza Line-Item Shipment Tracking & Dispatches', 'essenza-fulfillment' ),
            [ __CLASS__, 'render_shipment_meta_box' ],
            $screen,
            'normal',
            'high'
        );
    }

    /**
     * Render Shipment Meta Box Content
     */
    public static function render_shipment_meta_box( $post_or_order_object ) {
        $order = ( $post_or_order_object instanceof \WC_Order ) ? $post_or_order_object : wc_get_order( $post_or_order_object->ID );
        if ( ! $order ) return;

        $shipments = $order->get_meta( '_essenza_shipments' ) ?: [];

        echo '<div style="font-family:sans-serif;">';
        echo '<p style="color:#64748b;font-size:13px;margin-bottom:12px;">' . esc_html__( 'Manage independent line-item shipments without creating separate billable orders. The customer paid once under this master order.', 'essenza-fulfillment' ) . '</p>';

        echo '<table class="widefat fixed striped" style="margin-bottom:16px;">';
        echo '<thead><tr>';
        echo '<th style="width:30%;"><strong>' . esc_html__( 'Item', 'essenza-fulfillment' ) . '</strong></th>';
        echo '<th style="width:20%;"><strong>' . esc_html__( 'Sourcing Origin', 'essenza-fulfillment' ) . '</strong></th>';
        echo '<th style="width:25%;"><strong>' . esc_html__( 'Carrier & Tracking #', 'essenza-fulfillment' ) . '</strong></th>';
        echo '<th style="width:25%;"><strong>' . esc_html__( 'Fulfillment Status', 'essenza-fulfillment' ) . '</strong></th>';
        echo '</tr></thead><tbody>';

        foreach ( $order->get_items() as $item_id => $item ) {
            $origin = $item->get_meta( '_fulfillment_origin' ) ?: 'in_house';
            $saved_tracking = $shipments[ $item_id ]['tracking'] ?? '';
            $saved_carrier  = $shipments[ $item_id ]['carrier'] ?? 'Australia Post';
            $saved_status   = $shipments[ $item_id ]['status'] ?? 'pending';

            $origin_badge = match ( $origin ) {
                self::ORIGIN_IMPORT => '<span style="color:#b45309;font-weight:600;">Custom Import (China)</span>',
                self::ORIGIN_LOCAL  => '<span style="color:#0284c7;font-weight:600;">Local Supplier</span>',
                default             => '<span style="color:#15803d;font-weight:600;">Warehouse</span>',
            };

            echo '<tr>';
            echo '<td><strong>' . esc_html( $item->get_name() ) . '</strong> &times; ' . esc_html( $item->get_quantity() ) . '</td>';
            echo '<td>' . wp_kses_post( $origin_badge ) . '</td>';
            echo '<td>';
            echo '<input type="text" name="essenza_carrier[' . esc_attr( $item_id ) . ']" value="' . esc_attr( $saved_carrier ) . '" placeholder="Carrier (e.g. AusPost / Allied)" style="width:48%;margin-right:2%;font-size:12px;">';
            echo '<input type="text" name="essenza_tracking[' . esc_attr( $item_id ) . ']" value="' . esc_attr( $saved_tracking ) . '" placeholder="Tracking #" style="width:48%;font-size:12px;">';
            echo '</td>';
            echo '<td>';
            echo '<select name="essenza_status[' . esc_attr( $item_id ) . ']" style="width:100%;font-size:12px;">';
            echo '<option value="pending" ' . selected( $saved_status, 'pending', false ) . '>⏳ Pending / In Preparation</option>';
            echo '<option value="ordered" ' . selected( $saved_status, 'ordered', false ) . '>📋 Supplier / Factory Ordered</option>';
            echo '<option value="in_transit" ' . selected( $saved_status, 'in_transit', false ) . '>🚢 Sea / Road Transit</option>';
            echo '<option value="dispatched" ' . selected( $saved_status, 'dispatched', false ) . '>🚚 Dispatched / Out for Delivery</option>';
            echo '<option value="delivered" ' . selected( $saved_status, 'delivered', false ) . '>✅ Delivered</option>';
            echo '</select>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '<p style="text-align:right;"><small style="color:#64748b;">' . esc_html__( 'Click "Update Order" to save line-item shipment records and update customer records.', 'essenza-fulfillment' ) . '</small></p>';
        echo '</div>';
    }

    /**
     * Save Line-Item Shipment Data
     */
    public static function save_shipment_manager_data( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        if ( ! isset( $_POST['essenza_status'] ) || ! is_array( $_POST['essenza_status'] ) ) {
            return;
        }

        $shipments = [];
        $total_items = count( $_POST['essenza_status'] );
        $dispatched_count = 0;

        foreach ( $_POST['essenza_status'] as $item_id => $status ) {
            $carrier  = sanitize_text_field( $_POST['essenza_carrier'][ $item_id ] ?? '' );
            $tracking = sanitize_text_field( $_POST['essenza_tracking'][ $item_id ] ?? '' );

            $shipments[ $item_id ] = [
                'carrier'  => $carrier,
                'tracking' => $tracking,
                'status'   => sanitize_text_field( $status ),
                'updated'  => current_time( 'mysql' ),
            ];

            if ( in_array( $status, [ 'dispatched', 'delivered' ], true ) ) {
                $dispatched_count++;
            }
        }

        $order->update_meta_data( '_essenza_shipments', $shipments );

        // Automatically update master order status based on partial progress
        if ( $dispatched_count > 0 && $dispatched_count < $total_items ) {
            if ( $order->get_status() !== 'partially-shipped' ) {
                $order->set_status( 'partially-shipped', __( 'Automated: Some items dispatched. Order marked Partially Shipped.', 'essenza-fulfillment' ) );
            }
        } elseif ( $dispatched_count === $total_items && $total_items > 0 ) {
            if ( $order->get_status() !== 'completed' ) {
                $order->set_status( 'completed', __( 'Automated: All items marked dispatched/delivered.', 'essenza-fulfillment' ) );
            }
        }

        $order->save();
    }

    /**
     * Render Luxury Front-End Shipment Tracking Card on Customer Order View
     */
    public static function render_customer_order_shipments_tracker( $order ) {
        if ( ! $order ) return;

        $shipments = $order->get_meta( '_essenza_shipments' ) ?: [];
        if ( empty( $shipments ) ) return;

        echo '<section class="essenza-customer-tracker" style="margin-top:40px;background:#FFFFFF;border:1px solid #E8E5DF;border-radius:12px;padding:32px;box-shadow:0 4px 20px rgba(0,0,0,0.04);">';
        echo '<div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #F0EDE8;padding-bottom:18px;margin-bottom:24px;flex-wrap:wrap;gap:12px;">';
        echo '<div>';
        echo '<h3 style="font-family:\'Cormorant Garamond\',Georgia,serif;font-size:26px;color:#1C1C1C;margin:0 0 4px 0;">📦 Multi-Stage Delivery & Tracking Status</h3>';
        echo '<p style="margin:0;font-size:13px;color:#6E6D6B;">Your order items are sourced across distinct fulfillment origins and may arrive in separate shipments.</p>';
        echo '</div>';
        echo '<div style="background:#F9F8F6;border:1px solid #E8E5DF;padding:6px 14px;border-radius:6px;font-size:12px;font-weight:600;color:#C5A059;">';
        echo 'Master Order #' . esc_html( $order->get_id() ) . ' &bull; Single Payment Paid';
        echo '</div>';
        echo '</div>';

        echo '<div style="display:flex;flex-direction:column;gap:18px;">';

        foreach ( $order->get_items() as $item_id => $item ) {
            $origin = $item->get_meta( '_fulfillment_origin' ) ?: 'in_house';
            $shipment = $shipments[ $item_id ] ?? null;

            $carrier  = $shipment['carrier'] ?? 'Australia Courier';
            $tracking = $shipment['tracking'] ?? '';
            $status   = $shipment['status'] ?? 'pending';

            $origin_label = match ( $origin ) {
                self::ORIGIN_IMPORT => '🟡 Overseas Import (Sea Freight)',
                self::ORIGIN_LOCAL  => '🔵 Local Australian Workshop',
                default             => '🟢 In-House Sydney Warehouse',
            };

            $status_badge = match ( $status ) {
                'delivered'   => '<span style="background:#DCFCE7;color:#166534;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;">✅ Delivered</span>',
                'dispatched'  => '<span style="background:#E0F2FE;color:#0369A1;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;">🚚 Out for Delivery / Dispatched</span>',
                'in_transit'  => '<span style="background:#FEF3C7;color:#92400E;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;">🚢 Sea / Interstate Transit</span>',
                'ordered'     => '<span style="background:#F3E8FF;color:#6B21A8;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;">📋 In Production / Workshop Ordered</span>',
                default       => '<span style="background:#F1F5F9;color:#475569;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;">⏳ In Preparation</span>',
            };

            // Carrier Link formatting
            $tracking_html = '<span>' . esc_html( $carrier ) . '</span>';
            if ( ! empty( $tracking ) ) {
                $track_url = '#';
                if ( stripos( $carrier, 'australia post' ) !== false || stripos( $carrier, 'auspost' ) !== false ) {
                    $track_url = 'https://auspost.com.au/mypost/track/#/details/' . rawurlencode( $tracking );
                }
                $tracking_html .= ' &bull; <strong>Tracking:</strong> ';
                if ( $track_url !== '#' ) {
                    $tracking_html .= '<a href="' . esc_url( $track_url ) . '" target="_blank" rel="noopener noreferrer" style="color:#C5A059;text-decoration:underline;">' . esc_html( $tracking ) . '</a>';
                } else {
                    $tracking_html .= '<span style="font-family:monospace;color:#1C1C1C;">' . esc_html( $tracking ) . '</span>';
                }
            }

            echo '<div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;background:#F9F8F6;border:1px solid #E8E5DF;border-radius:8px;flex-wrap:wrap;gap:12px;">';
            echo '<div style="flex:1;min-width:240px;">';
            echo '<div style="font-weight:600;font-size:15px;color:#1C1C1C;margin-bottom:4px;">' . esc_html( $item->get_name() ) . ' &times; ' . esc_html( $item->get_quantity() ) . '</div>';
            echo '<div style="font-size:12px;color:#6E6D6B;display:flex;align-items:center;gap:10px;">';
            echo '<span>' . esc_html( $origin_label ) . '</span> &bull; ';
            echo wp_kses_post( $tracking_html );
            echo '</div>';
            echo '</div>';

            echo '<div>' . wp_kses_post( $status_badge ) . '</div>';
            echo '</div>';
        }

        echo '</div>';

        echo '<div style="margin-top:20px;padding-top:16px;border-top:1px solid #F0EDE8;font-size:12px;color:#9B9A97;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">';
        echo '<span>All shipments protected under Essenza White-Glove Transit Insurance.</span>';
        echo '<span>Questions? Contact Concierge: <a href="mailto:concierge@essenzafurniture.com.au" style="color:#C5A059;">concierge@essenzafurniture.com.au</a></span>';
        echo '</div>';
        echo '</section>';
    }

    /**
     * Handle Tokenized 1-Click Magic Link Authentication
     */
    public static function handle_magic_auth_login() {
        if ( isset( $_GET['essenza_auth'] ) && isset( $_GET['uid'] ) ) {
            $token   = sanitize_text_field( $_GET['essenza_auth'] );
            $user_id = intval( $_GET['uid'] );

            $user = get_user_by( 'id', $user_id );
            if ( ! $user ) {
                return;
            }

            $stored_token = get_user_meta( $user_id, '_essenza_magic_token', true );
            $expiry       = intval( get_user_meta( $user_id, '_essenza_magic_token_exp', true ) );

            if ( ! empty( $stored_token ) && hash_equals( $stored_token, $token ) && $expiry > time() ) {
                wp_set_current_user( $user_id );
                wp_set_auth_cookie( $user_id, true );
                delete_user_meta( $user_id, '_essenza_magic_token' );

                wc_add_notice( sprintf( __( 'Welcome back, %s! You have logged in securely via 1-Click Verification.', 'essenza-fulfillment' ), esc_html( $user->display_name ) ), 'notice' );
                wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
                exit;
            } else {
                wc_add_notice( __( 'Your 1-click login link has expired or is invalid. Please enter your email below to receive a new link.', 'essenza-fulfillment' ), 'error' );
            }
        }
    }

    /**
     * Render Passwordless Magic Link Request Form on Login Screen
     */
    public static function render_magic_link_request_ui() {
        echo '<div class="essenza-magic-link-box" style="margin-top:24px;padding:20px;background:#F9F8F6;border:1px solid #E8E5DF;border-radius:8px;">';
        echo '<h4 style="font-family:\'Cormorant Garamond\',Georgia,serif;font-size:18px;margin:0 0 6px 0;color:#1C1C1C;">🔑 Passwordless 1-Click Login</h4>';
        echo '<p style="font-size:13px;color:#6E6D6B;margin:0 0 14px 0;">Existing Essenza or Shopify customer? Enter your email address to receive an instant secure login link.</p>';
        echo '<form method="post" action="" style="display:flex;gap:10px;">';
        wp_nonce_field( 'essenza_request_magic_link', 'essenza_magic_nonce' );
        echo '<input type="email" name="essenza_customer_email" placeholder="your.email@example.com.au" required style="flex:1;padding:10px 14px;border:1px solid #E8E5DF;border-radius:4px;font-size:13px;">';
        echo '<button type="submit" name="essenza_send_magic" value="1" style="background:#1C1C1C;color:#FFFFFF;padding:10px 18px;border-radius:4px;font-size:12px;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;">Send Link</button>';
        echo '</form>';
        echo '</div>';
    }

    /**
     * Process Customer Magic Link Request
     */
    public static function process_magic_link_request() {
        if ( isset( $_POST['essenza_send_magic'] ) && isset( $_POST['essenza_customer_email'] ) ) {
            check_admin_referer( 'essenza_request_magic_link', 'essenza_magic_nonce' );

            $email = sanitize_email( wp_unslash( $_POST['essenza_customer_email'] ) );
            $user  = get_user_by( 'email', $email );

            if ( $user ) {
                $token = hash_hmac( 'sha256', "{$user->ID}|{$email}|" . wp_salt( 'auth' ), wp_salt( 'secure_auth' ) );
                update_user_meta( $user->ID, '_essenza_magic_token', $token );
                update_user_meta( $user->ID, '_essenza_magic_token_exp', time() + 3600 ); // 1 hour validity

                $magic_url = add_query_arg( [
                    'essenza_auth' => $token,
                    'uid'          => $user->ID,
                ], wc_get_page_permalink( 'myaccount' ) );

                $subject = __( 'Your Secure 1-Click Login Link - Essenza Furniture', 'essenza-fulfillment' );
                $message = "Hello {$user->first_name},\n\nClick the link below to securely log into your Essenza Furniture account:\n\n{$magic_url}\n\nThis link will expire in 60 minutes.\n\nWarm regards,\nEssenza Furniture Client Concierge";

                wp_mail( $email, $subject, $message );
            }

            // Always display positive notice to prevent user enumeration
            wc_add_notice( __( 'If an account exists with that email, a secure 1-click login link has been dispatched to your inbox.', 'essenza-fulfillment' ), 'notice' );
            wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
            exit;
        }
    }

    /**
     * Add "Download Tax Invoice" Action on Customer My Account Orders List
     */
    public static function add_tax_invoice_order_action( $actions, $order ) {
        if ( $order && in_array( $order->get_status(), [ 'processing', 'partially-shipped', 'completed' ], true ) ) {
            $actions['tax_invoice'] = [
                'url'  => wp_nonce_url( add_query_arg( [
                    'essenza_action' => 'tax_invoice',
                    'order_id'       => $order->get_id(),
                ], home_url( '/' ) ), 'essenza_tax_invoice' ),
                'name' => __( 'Tax Invoice (PDF/Print)', 'essenza-fulfillment' ),
            ];
        }
        return $actions;
    }

    /**
     * Render Invoice Download Button on Customer Order Detail View
     */
    public static function render_invoice_download_button( $order ) {
        if ( ! $order ) return;

        $invoice_url = wp_nonce_url( add_query_arg( [
            'essenza_action' => 'tax_invoice',
            'order_id'       => $order->get_id(),
        ], home_url( '/' ) ), 'essenza_tax_invoice' );

        echo '<div style="margin-top:20px;text-align:right;">';
        echo '<a href="' . esc_url( $invoice_url ) . '" target="_blank" style="display:inline-flex;align-items:center;gap:8px;padding:12px 20px;background:#1C1C1C;color:#FFFFFF;border-radius:4px;font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;text-decoration:none;">';
        echo '<span>📄 View Australian Tax Invoice (ABN 84 629 104 293)</span>';
        echo '</a>';
        echo '</div>';
    }

    /**
     * Handle Australian Compliant Master Tax Invoice (GST 10%) View & Print
     */
    public static function handle_tax_invoice_download() {
        if ( isset( $_GET['essenza_action'] ) && $_GET['essenza_action'] === 'tax_invoice' && isset( $_GET['order_id'] ) ) {
            $nonce = $_GET['_wpnonce'] ?? '';
            if ( ! wp_verify_nonce( $nonce, 'essenza_tax_invoice' ) && ! current_user_can( 'manage_woocommerce' ) ) {
                wp_die( 'Security verification failed or link has expired.' );
            }

            $order_id = intval( $_GET['order_id'] );
            $order    = wc_get_order( $order_id );

            if ( ! $order ) {
                wp_die( 'Order not found.' );
            }

            // Security check: Only customer or admin can view
            if ( ! current_user_can( 'manage_woocommerce' ) && get_current_user_id() !== $order->get_customer_id() ) {
                wp_die( 'Access denied. You do not have permission to view this invoice.' );
            }

            $shipments = $order->get_meta( '_essenza_shipments' ) ?: [];
            $total_paid = floatval( $order->get_total() );
            $gst_total  = floatval( $order->get_total_tax() );
            if ( $gst_total <= 0 ) {
                // If tax not computed separately, calculate standard Australian 1/11th inclusive GST
                $gst_total = round( $total_paid / 11, 2 );
            }
            $subtotal_ex_gst = $total_paid - $gst_total;

            ?>
            <!DOCTYPE html>
            <html lang="en-AU">
            <head>
                <meta charset="UTF-8">
                <title>TAX INVOICE #ESS-<?php echo esc_html( $order->get_id() ); ?> - Essenza Furniture</title>
                <style>
                    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; color: #1c1c1c; margin: 0; padding: 40px; background: #fdfdfd; }
                    .invoice-container { max-width: 820px; margin: 0 auto; background: #ffffff; padding: 48px; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 4px 16px rgba(0,0,0,0.04); }
                    .header-grid { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #1c1c1c; padding-bottom: 24px; margin-bottom: 30px; }
                    .brand-name { font-size: 28px; font-weight: 700; letter-spacing: 0.15em; text-transform: uppercase; margin: 0; }
                    .brand-abn { font-size: 13px; color: #64748b; margin-top: 4px; }
                    .doc-title { font-size: 24px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: #1c1c1c; text-align: right; }
                    .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-bottom: 36px; }
                    .meta-block h4 { font-size: 12px; text-transform: uppercase; letter-spacing: 0.1em; color: #94a3b8; margin: 0 0 8px 0; }
                    .meta-block p { font-size: 14px; line-height: 1.6; margin: 0; color: #334155; }
                    table.invoice-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
                    table.invoice-table th { background: #f8fafc; border-bottom: 2px solid #cbd5e1; text-align: left; padding: 12px 14px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.08em; color: #475569; }
                    table.invoice-table td { padding: 14px; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
                    .origin-tag { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; margin-top: 4px; }
                    .origin-in_house { background: #dcfce7; color: #166534; }
                    .origin-local_supplier { background: #e0f2fe; color: #0369a1; }
                    .origin-overseas_import { background: #fef3c7; color: #92400e; }
                    .totals-box { margin-left: auto; width: 340px; border-top: 2px solid #1c1c1c; padding-top: 14px; }
                    .totals-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px; }
                    .totals-row.grand-total { font-size: 18px; font-weight: 700; border-top: 1px solid #e2e8f0; padding-top: 10px; margin-top: 6px; }
                    .print-btn-bar { text-align: right; margin-bottom: 20px; }
                    .print-btn { background: #1c1c1c; color: #ffffff; border: none; padding: 10px 20px; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; border-radius: 4px; cursor: pointer; }
                    @media print { .print-btn-bar { display: none; } body { padding: 0; background: #fff; } .invoice-container { border: none; box-shadow: none; padding: 0; } }
                </style>
            </head>
            <body>
                <div class="invoice-container">
                    <div class="print-btn-bar">
                        <button class="print-btn" onclick="window.print()">🖨️ Print / Save as PDF</button>
                    </div>

                    <div class="header-grid">
                        <div>
                            <h1 class="brand-name">ESSENZA FURNITURE</h1>
                            <div class="brand-abn">
                                <strong>ABN:</strong> 84 629 104 293 &bull; Australian GST Registered<br>
                                42 Elizabeth Bay Road, Sydney NSW 2011, Australia<br>
                                concierge@essenzafurniture.com.au &bull; www.essenzafurniture.com.au
                            </div>
                        </div>
                        <div>
                            <div class="doc-title">TAX INVOICE</div>
                            <div style="font-size:13px;color:#64748b;margin-top:6px;text-align:right;">
                                <strong>Invoice #:</strong> ESS-INV-<?php echo esc_html( $order->get_id() ); ?><br>
                                <strong>Date:</strong> <?php echo esc_html( $order->get_date_created() ? $order->get_date_created()->date( 'd F Y' ) : gmdate( 'd F Y' ) ); ?><br>
                                <strong>Payment:</strong> Paid via <?php echo esc_html( $order->get_payment_method_title() ?: 'Credit Card / Electronic Funds' ); ?>
                            </div>
                        </div>
                    </div>

                    <div class="meta-grid">
                        <div class="meta-block">
                            <h4>Billed & Delivered To:</h4>
                            <p>
                                <strong><?php echo esc_html( $order->get_formatted_billing_full_name() ); ?></strong><br>
                                <?php echo esc_html( $order->get_shipping_address_1() ); ?><br>
                                <?php echo esc_html( $order->get_shipping_city() . ', ' . $order->get_shipping_state() . ' ' . $order->get_shipping_postcode() ); ?><br>
                                Australia<br>
                                Phone: <?php echo esc_html( $order->get_billing_phone() ); ?> &bull; Email: <?php echo esc_html( $order->get_billing_email() ); ?>
                            </p>
                        </div>
                        <div class="meta-block">
                            <h4>Fulfillment Package Log:</h4>
                            <p style="font-size:13px;">
                                <?php
                                if ( ! empty( $shipments ) ) {
                                    foreach ( $shipments as $iid => $sh ) {
                                        $pname = $order->get_item( $iid ) ? $order->get_item( $iid )->get_name() : "Item #{$iid}";
                                        echo "&bull; <strong>" . esc_html( $pname ) . ":</strong> " . esc_html( $sh['carrier'] ?? 'Courier' );
                                        if ( ! empty( $sh['tracking'] ) ) {
                                            echo " [#" . esc_html( $sh['tracking'] ) . "]";
                                        }
                                        echo " &bull; <em>" . esc_html( ucfirst( $sh['status'] ?? 'processing' ) ) . "</em><br>";
                                    }
                                } else {
                                    echo "Standard Order Fulfillment (Consolidated)";
                                }
                                ?>
                            </p>
                        </div>
                    </div>

                    <table class="invoice-table">
                        <thead>
                            <tr>
                                <th style="width:50%;">Item Description & Sourcing Origin</th>
                                <th style="width:12%;text-align:center;">Qty</th>
                                <th style="width:18%;text-align:right;">Unit Price</th>
                                <th style="width:20%;text-align:right;">Total (AUD)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $order->get_items() as $item_id => $item ) :
                                $origin = $item->get_meta( '_fulfillment_origin' ) ?: 'in_house';
                                $origin_title = match( $origin ) {
                                    'overseas_import' => '🟡 Custom Overseas Import (Sea Freight)',
                                    'local_supplier'  => '🔵 Local Australian Workshop Maker',
                                    default           => '🟢 Sydney Distribution Warehouse',
                                };
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $item->get_name() ); ?></strong><br>
                                    <span class="origin-tag origin-<?php echo esc_attr( $origin ); ?>"><?php echo esc_html( $origin_title ); ?></span>
                                </td>
                                <td style="text-align:center;"><?php echo esc_html( $item->get_quantity() ); ?></td>
                                <td style="text-align:right;">$<?php echo number_format( floatval( $item->get_subtotal() ) / max( 1, $item->get_quantity() ), 2 ); ?></td>
                                <td style="text-align:right;">$<?php echo number_format( floatval( $item->get_total() ), 2 ); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="totals-box">
                        <div class="totals-row">
                            <span>Subtotal (Ex-GST):</span>
                            <span>$<?php echo number_format( $subtotal_ex_gst - floatval( $order->get_shipping_total() ), 2 ); ?> AUD</span>
                        </div>
                        <div class="totals-row">
                            <span>Unified Delivery Freight:</span>
                            <span>$<?php echo number_format( floatval( $order->get_shipping_total() ), 2 ); ?> AUD</span>
                        </div>
                        <div class="totals-row">
                            <span>Australian GST (10% Included):</span>
                            <span>$<?php echo number_format( $gst_total, 2 ); ?> AUD</span>
                        </div>
                        <div class="totals-row grand-total">
                            <span>TOTAL AMOUNT (AUD):</span>
                            <span>$<?php echo number_format( $total_paid, 2 ); ?> AUD</span>
                        </div>
                    </div>

                    <div style="margin-top:48px;padding-top:20px;border-top:1px solid #e2e8f0;font-size:12px;color:#94a3b8;text-align:center;">
                        This document constitutes a valid Tax Invoice for Australian Goods and Services Tax (GST) purposes under the A New Tax System (Goods and Services Tax) Act 1999.<br>
                        Essenza Furniture Pty Ltd &bull; ABN 84 629 104 293 &bull; Sydney, New South Wales, Australia.
                    </div>
                </div>
            </body>
            </html>
            <?php
            exit;
        }
    }

    /**
     * Setup default shipping zone & flat rates on activation if none exist
     */
    public static function on_activation() {
        // Ensure default Australia shipping zone has a Flat Rate
        $delivery_zones = WC_Shipping_Zones::get_zones();
        if ( empty( $delivery_zones ) ) {
            $zone = new WC_Shipping_Zone();
            $zone->set_zone_name( 'Australia (Domestic)' );
            $zone->set_zone_order( 1 );
            $zone->add_location( 'AU', 'country' );
            $zone->save();

            // Add Flat Rate method
            $instance_id = $zone->add_shipping_method( 'flat_rate' );
            $shipping_method = WC_Shipping_Zones::get_shipping_method( $instance_id );
            if ( $shipping_method ) {
                $shipping_method->init_instance_settings();
                $shipping_method->instance_settings['title'] = 'Furniture Freight / Standard Courier';
                $shipping_method->instance_settings['cost']  = '45.00';
                update_option( $shipping_method->get_instance_option_key(), $shipping_method->instance_settings );
            }
        }
    }
}

Essenza_Fulfillment_Core::init();
