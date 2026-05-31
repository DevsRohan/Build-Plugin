<?php
namespace suspended_Order_Notifier\Integrations;

use suspended_Order_Notifier\Notifications\Manager;

if ( ! defined( 'ABSPATH' ) ) exit;

class WooCommerce {

    private $manager;

    public function __construct() {
        $this->manager = new Manager();

        if ( 'yes' === get_option( 'won_enable_order_alerts', 'yes' ) ) {
            add_action( 'woocommerce_new_order', array( $this, 'new_order' ), 10, 2 );
        }
        if ( 'yes' === get_option( 'won_enable_refund_alerts', 'yes' ) ) {
            add_action( 'woocommerce_refund_created', array( $this, 'refund_created' ), 10, 2 );
        }
        if ( 'yes' === get_option( 'won_enable_stock_alerts', 'yes' ) ) {
            add_action( 'woocommerce_low_stock', array( $this, 'low_stock' ) );
            add_action( 'woocommerce_no_stock', array( $this, 'no_stock' ) );
            add_action( 'woocommerce_product_set_stock', array( $this, 'stock_change' ) );
            add_action( 'woocommerce_variation_set_stock', array( $this, 'stock_change' ) );
        }
        if ( 'yes' === get_option( 'won_enable_abandoned_cart', 'yes' ) ) {
            add_action( 'woocommerce_cart_updated', array( $this, 'track_cart' ) );
            add_action( 'woocommerce_add_to_cart', array( $this, 'track_cart' ) );
            add_action( 'woocommerce_thankyou', array( $this, 'cart_recovered' ) );
        }
    }

    public function new_order( $order_id, $order = null ) {
        if ( ! $order ) $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        if ( $order->get_meta( '_won_notified' ) ) return;

        $items = array();
        foreach ( $order->get_items() as $item ) {
            $items[] = sprintf( '• %s x%d — %s', $item->get_name(), $item->get_quantity(),
                strip_tags( wc_price( $item->get_total(), array( 'currency' => $order->get_currency() ) ) ) );
        }

        $shipping = wp_strip_all_tags( str_replace( '<br/>', ', ', $order->get_formatted_shipping_address() ?: $order->get_formatted_billing_address() ) );

        $data = array(
            'order_id'         => $order->get_id(),
            'customer_name'    => $order->get_formatted_billing_full_name(),
            'customer_phone'   => $order->get_billing_phone(),
            'customer_email'   => $order->get_billing_email(),
            'order_items'      => implode( "\n", $items ),
            'order_total'      => strip_tags( $order->get_formatted_order_total() ),
            'payment_method'   => $order->get_payment_method_title(),
            'shipping_address' => $shipping,
            'order_date'       => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i' ) : current_time( 'Y-m-d H:i' ),
        );

        $this->manager->queue_notification( 'order', $order_id, $data );
        $order->update_meta_data( '_won_notified', current_time( 'mysql' ) );
        $order->save();
    }

    public function refund_created( $order_id, $refund_id ) {
        $order = wc_get_order( $order_id );
        $refund = wc_get_order( $refund_id );
        if ( ! $order || ! $refund ) return;

        $data = array(
            'order_id'      => $order_id,
            'customer_name' => $order->get_formatted_billing_full_name(),
            'refund_amount' => strip_tags( wc_price( $refund->get_amount(), array( 'currency' => $order->get_currency() ) ) ),
            'refund_reason' => $refund->get_reason() ?: 'No reason provided',
            'refund_date'   => current_time( 'Y-m-d H:i' ),
        );

        $this->manager->queue_notification( 'refund', $order_id, $data );
    }

    public function low_stock( $product ) { $this->stock_alert( $product, 'LOW STOCK' ); }
    public function no_stock( $product ) { $this->stock_alert( $product, 'OUT OF STOCK' ); }

    public function stock_change( $product ) {
        if ( ! $product->managing_stock() ) return;
        $qty = $product->get_stock_quantity();
        $threshold = (int) get_option( 'won_stock_threshold', 5 );

        if ( $qty <= $threshold && $qty > 0 ) {
            if ( get_transient( 'won_stock_' . $product->get_id() ) ) return;
            $this->stock_alert( $product, 'LOW STOCK' );
            set_transient( 'won_stock_' . $product->get_id(), 1, HOUR_IN_SECONDS );
        } elseif ( $qty <= 0 ) {
            $this->stock_alert( $product, 'OUT OF STOCK' );
        }
    }

    private function stock_alert( $product, $status ) {
        $data = array(
            'product_name'   => $product->get_name(),
            'product_sku'    => $product->get_sku() ?: 'N/A',
            'stock_quantity' => $product->get_stock_quantity(),
            'stock_status'   => $status,
            'alert_time'     => current_time( 'Y-m-d H:i' ),
        );
        $this->manager->queue_notification( 'stock', $product->get_id(), $data );
    }

    public function track_cart() {
        if ( is_admin() || ! function_exists( 'WC' ) || is_null( WC()->cart ) || WC()->cart->is_empty() ) return;
        if ( is_null( WC()->session ) ) return;

        $session_id = WC()->session->get_customer_id();
        if ( ! $session_id ) return;

        $cart = WC()->cart;
        $user_id = get_current_user_id();
        $items = array();
        foreach ( $cart->get_cart() as $item ) {
            $items[] = array( 'name' => $item['data']->get_name(), 'qty' => $item['quantity'], 'total' => $item['line_total'] );
        }

        $email = ''; $name = ''; $phone = '';
        if ( $user_id ) {
            $user = get_userdata( $user_id );
            $email = $user->user_email;
            $name = $user->display_name;
            $phone = get_user_meta( $user_id, 'billing_phone', true );
        }

        $cart_model = new \suspended_Order_Notifier\Database\AbandonedCart();
        $cart_model->upsert( array(
            'session_id' => $session_id, 'user_id' => $user_id ?: null,
            'customer_email' => $email, 'customer_name' => $name, 'customer_phone' => $phone,
            'cart_contents' => wp_json_encode( $items ), 'cart_total' => $cart->get_cart_contents_total(),
        ) );
    }

    public function cart_recovered( $order_id ) {
        if ( ! function_exists( 'WC' ) || is_null( WC()->session ) ) return;
        $session_id = WC()->session->get_customer_id();
        if ( $session_id ) {
            ( new \suspended_Order_Notifier\Database\AbandonedCart() )->mark_recovered( $session_id );
        }
    }
}
