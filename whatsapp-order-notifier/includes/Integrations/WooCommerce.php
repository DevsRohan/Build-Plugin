<?php
/**
 * WooCommerce Integration.
 *
 * @package suspended_Order_Notifier\Integrations
 * @since 1.0.0
 */

namespace suspended_Order_Notifier\Integrations;

use suspended_Order_Notifier\Notifications\Manager as NotificationManager;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles all WooCommerce event hooks for notifications.
 *
 * @since 1.0.0
 */
class WooCommerce {

    /**
     * Notification manager instance.
     *
     * @var NotificationManager
     */
    private $notification_manager;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->notification_manager = new NotificationManager();
        $this->init_hooks();
    }

    /**
     * Initialize WooCommerce hooks.
     *
     * @return void
     */
    private function init_hooks() {
        // Order notifications.
        if ( 'yes' === get_option( 'won_enable_order_alerts', 'yes' ) ) {
            add_action( 'woocommerce_new_order', array( $this, 'handle_new_order' ), 10, 2 );
            add_action( 'woocommerce_order_status_changed', array( $this, 'handle_order_status_change' ), 10, 4 );
        }

        // Refund notifications.
        if ( 'yes' === get_option( 'won_enable_refund_alerts', 'yes' ) ) {
            add_action( 'woocommerce_order_refunded', array( $this, 'handle_refund' ), 10, 2 );
            add_action( 'woocommerce_refund_created', array( $this, 'handle_refund_created' ), 10, 2 );
        }

        // Stock notifications.
        if ( 'yes' === get_option( 'won_enable_stock_alerts', 'yes' ) ) {
            add_action( 'woocommerce_low_stock', array( $this, 'handle_low_stock' ) );
            add_action( 'woocommerce_no_stock', array( $this, 'handle_no_stock' ) );
            add_action( 'woocommerce_product_set_stock', array( $this, 'handle_stock_change' ) );
            add_action( 'woocommerce_variation_set_stock', array( $this, 'handle_stock_change' ) );
        }

        // Cart tracking for abandoned cart feature.
        if ( 'yes' === get_option( 'won_enable_abandoned_cart', 'yes' ) ) {
            add_action( 'woocommerce_cart_updated', array( $this, 'track_cart' ) );
            add_action( 'woocommerce_add_to_cart', array( $this, 'track_cart' ) );
            add_action( 'woocommerce_cart_item_removed', array( $this, 'track_cart' ) );
            add_action( 'woocommerce_thankyou', array( $this, 'handle_order_complete_cart' ), 10, 1 );
        }
    }

    /**
     * Handle new order creation.
     *
     * @param int       $order_id Order ID.
     * @param \WC_Order $order    Order object.
     * @return void
     */
    public function handle_new_order( $order_id, $order = null ) {
        if ( ! $order ) {
            $order = wc_get_order( $order_id );
        }

        if ( ! $order ) {
            return;
        }

        // Prevent duplicate notifications.
        $already_notified = $order->get_meta( '_won_new_order_notified' );
        if ( $already_notified ) {
            return;
        }

        // Build order data for template.
        $data = $this->get_order_template_data( $order );

        // Queue the notification.
        $this->notification_manager->queue_notification(
            'order',
            $order_id,
            $data
        );

        // Mark as notified.
        $order->update_meta_data( '_won_new_order_notified', current_time( 'mysql' ) );
        $order->save();

        /**
         * Action fired after new order notification is queued.
         *
         * @param int       $order_id Order ID.
         * @param \WC_Order $order    Order object.
         */
        do_action( 'won_after_new_order_notification', $order_id, $order );
    }

    /**
     * Handle order status changes.
     *
     * @param int       $order_id   Order ID.
     * @param string    $old_status Old status.
     * @param string    $new_status New status.
     * @param \WC_Order $order      Order object.
     * @return void
     */
    public function handle_order_status_change( $order_id, $old_status, $new_status, $order ) {
        // Only notify for significant status changes.
        $notify_statuses = apply_filters(
            'won_notify_status_changes',
            array( 'processing', 'completed', 'cancelled', 'failed', 'on-hold' )
        );

        if ( ! in_array( $new_status, $notify_statuses, true ) ) {
            return;
        }

        // Skip if this is the initial order creation (handled by handle_new_order).
        if ( 'pending' === $old_status && 'processing' === $new_status ) {
            return;
        }

        $data = $this->get_order_template_data( $order );
        $data['old_status'] = ucfirst( $old_status );
        $data['new_status'] = ucfirst( $new_status );

        $message = sprintf(
            "📋 *Order Status Update*\n\n🛒 Order #%s\n👤 %s\n\n📊 Status: %s → *%s*\n💰 Total: %s\n\n🕐 %s",
            $order_id,
            $data['customer_name'],
            ucfirst( $old_status ),
            ucfirst( $new_status ),
            $data['order_total'],
            current_time( 'Y-m-d H:i:s' )
        );

        $this->notification_manager->queue_notification(
            'order_status',
            $order_id,
            $data,
            $message
        );
    }

    /**
     * Handle refund creation.
     *
     * @param int $order_id  Order ID.
     * @param int $refund_id Refund ID.
     * @return void
     */
    public function handle_refund_created( $order_id, $refund_id ) {
        $order  = wc_get_order( $order_id );
        $refund = wc_get_order( $refund_id );

        if ( ! $order || ! $refund ) {
            return;
        }

        $data = array(
            'order_id'       => $order_id,
            'customer_name'  => $order->get_formatted_billing_full_name(),
            'customer_email' => $order->get_billing_email(),
            'customer_phone' => $order->get_billing_phone(),
            'refund_amount'  => wc_price( $refund->get_amount(), array( 'currency' => $order->get_currency() ) ),
            'refund_reason'  => $refund->get_reason() ?: __( 'No reason provided', 'suspended-order-notifier' ),
            'order_total'    => $order->get_formatted_order_total(),
            'refund_date'    => current_time( 'Y-m-d H:i:s' ),
        );

        $this->notification_manager->queue_notification(
            'refund',
            $order_id,
            $data
        );

        /**
         * Action fired after refund notification is queued.
         *
         * @param int $order_id  Order ID.
         * @param int $refund_id Refund ID.
         */
        do_action( 'won_after_refund_notification', $order_id, $refund_id );
    }

    /**
     * Handle refund event (legacy hook).
     *
     * @param int $order_id  Order ID.
     * @param int $refund_id Refund ID.
     * @return void
     */
    public function handle_refund( $order_id, $refund_id ) {
        // This is handled by handle_refund_created to avoid duplication.
        // Kept for backwards compatibility with older WooCommerce versions.
    }

    /**
     * Handle low stock notification.
     *
     * @param \WC_Product $product Product object.
     * @return void
     */
    public function handle_low_stock( $product ) {
        $this->send_stock_alert( $product, 'low' );
    }

    /**
     * Handle no stock notification.
     *
     * @param \WC_Product $product Product object.
     * @return void
     */
    public function handle_no_stock( $product ) {
        $this->send_stock_alert( $product, 'out' );
    }

    /**
     * Handle stock quantity change.
     *
     * @param \WC_Product $product Product object.
     * @return void
     */
    public function handle_stock_change( $product ) {
        if ( ! $product->managing_stock() ) {
            return;
        }

        $stock_quantity = $product->get_stock_quantity();
        $threshold      = (int) get_option( 'won_stock_threshold', 5 );

        // Only alert if stock falls below threshold.
        if ( $stock_quantity <= $threshold && $stock_quantity > 0 ) {
            // Prevent duplicate alerts within 1 hour.
            $last_alert = get_transient( 'won_stock_alert_' . $product->get_id() );
            if ( $last_alert ) {
                return;
            }

            $this->send_stock_alert( $product, 'low' );
            set_transient( 'won_stock_alert_' . $product->get_id(), true, HOUR_IN_SECONDS );
        } elseif ( $stock_quantity <= 0 ) {
            $this->send_stock_alert( $product, 'out' );
        }
    }

    /**
     * Send a stock alert notification.
     *
     * @param \WC_Product $product    Product object.
     * @param string      $alert_type Type of alert (low|out).
     * @return void
     */
    private function send_stock_alert( $product, $alert_type = 'low' ) {
        $data = array(
            'product_name'   => $product->get_name(),
            'product_id'     => $product->get_id(),
            'product_sku'    => $product->get_sku() ?: 'N/A',
            'stock_quantity' => $product->get_stock_quantity(),
            'stock_status'   => 'out' === $alert_type
                ? __( 'OUT OF STOCK', 'suspended-order-notifier' )
                : __( 'LOW STOCK', 'suspended-order-notifier' ),
            'alert_type'     => $alert_type,
            'alert_time'     => current_time( 'Y-m-d H:i:s' ),
            'product_url'    => get_edit_post_link( $product->get_id(), 'raw' ),
        );

        $this->notification_manager->queue_notification(
            'stock',
            $product->get_id(),
            $data
        );

        /**
         * Action fired after stock alert notification is queued.
         *
         * @param \WC_Product $product    Product object.
         * @param string      $alert_type Alert type.
         */
        do_action( 'won_after_stock_alert', $product, $alert_type );
    }

    /**
     * Track cart updates for abandoned cart detection.
     *
     * @return void
     */
    public function track_cart() {
        // Don't track for admin or empty carts.
        if ( is_admin() || ! function_exists( 'WC' ) || is_null( WC()->cart ) ) {
            return;
        }

        $cart = WC()->cart;

        if ( $cart->is_empty() ) {
            return;
        }

        // Get session ID.
        $session_id = $this->get_cart_session_id();
        if ( ! $session_id ) {
            return;
        }

        // Get customer info.
        $customer = WC()->customer;
        $user_id  = get_current_user_id();

        $customer_email = '';
        $customer_name  = '';
        $customer_phone = '';

        if ( $user_id ) {
            $user = get_userdata( $user_id );
            $customer_email = $user->user_email;
            $customer_name  = $user->display_name;
            $customer_phone = get_user_meta( $user_id, 'billing_phone', true );
        } elseif ( $customer ) {
            $customer_email = $customer->get_billing_email();
            $customer_name  = trim( $customer->get_billing_first_name() . ' ' . $customer->get_billing_last_name() );
            $customer_phone = $customer->get_billing_phone();
        }

        // Build cart contents.
        $cart_items = array();
        foreach ( $cart->get_cart() as $item ) {
            $product = $item['data'];
            $cart_items[] = array(
                'product_id'   => $item['product_id'],
                'variation_id' => $item['variation_id'] ?? 0,
                'name'         => $product->get_name(),
                'quantity'     => $item['quantity'],
                'price'        => $product->get_price(),
                'total'        => $item['line_total'],
            );
        }

        // Save to database.
        $cart_model = new \suspended_Order_Notifier\Database\AbandonedCart();
        $cart_model->upsert(
            array(
                'session_id'     => $session_id,
                'user_id'        => $user_id ?: null,
                'customer_email' => $customer_email,
                'customer_name'  => $customer_name,
                'customer_phone' => $customer_phone,
                'cart_contents'  => wp_json_encode( $cart_items ),
                'cart_total'     => $cart->get_cart_contents_total(),
                'currency'       => get_woocommerce_currency(),
            )
        );
    }

    /**
     * Handle order completion - mark cart as recovered.
     *
     * @param int $order_id Order ID.
     * @return void
     */
    public function handle_order_complete_cart( $order_id ) {
        $session_id = $this->get_cart_session_id();

        if ( $session_id ) {
            $cart_model = new \suspended_Order_Notifier\Database\AbandonedCart();
            $cart_model->mark_recovered( $session_id );
        }
    }

    /**
     * Get order data for template rendering.
     *
     * @param \WC_Order $order Order object.
     * @return array
     */
    private function get_order_template_data( $order ) {
        // Build order items string.
        $items_list = array();
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            $items_list[] = sprintf(
                '• %s x%d — %s',
                $item->get_name(),
                $item->get_quantity(),
                wc_price( $item->get_total(), array( 'currency' => $order->get_currency() ) )
            );
        }

        // Build shipping address.
        $shipping_address = $order->get_formatted_shipping_address();
        if ( empty( $shipping_address ) ) {
            $shipping_address = $order->get_formatted_billing_address();
        }
        // Convert HTML to plain text.
        $shipping_address = wp_strip_all_tags( str_replace( '<br/>', ', ', $shipping_address ) );

        return array(
            'order_id'         => $order->get_id(),
            'order_number'     => $order->get_order_number(),
            'customer_name'    => $order->get_formatted_billing_full_name(),
            'customer_email'   => $order->get_billing_email(),
            'customer_phone'   => $order->get_billing_phone(),
            'order_items'      => implode( "\n", $items_list ),
            'order_total'      => $order->get_formatted_order_total(),
            'payment_method'   => $order->get_payment_method_title(),
            'shipping_method'  => $order->get_shipping_method(),
            'shipping_address' => $shipping_address,
            'billing_address'  => wp_strip_all_tags( str_replace( '<br/>', ', ', $order->get_formatted_billing_address() ) ),
            'order_date'       => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : current_time( 'Y-m-d H:i:s' ),
            'order_status'     => ucfirst( $order->get_status() ),
            'order_notes'      => $order->get_customer_note() ?: __( 'None', 'suspended-order-notifier' ),
            'currency'         => $order->get_currency(),
            'items_count'      => $order->get_item_count(),
        );
    }

    /**
     * Get the current cart session ID.
     *
     * @return string|false Session ID or false if not available.
     */
    private function get_cart_session_id() {
        if ( ! function_exists( 'WC' ) || is_null( WC()->session ) ) {
            return false;
        }

        $session_id = WC()->session->get_customer_id();

        if ( empty( $session_id ) ) {
            return false;
        }

        return $session_id;
    }
}
