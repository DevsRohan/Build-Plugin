<?php
/**
 * Abandoned Cart Handler.
 *
 * @package suspended_Order_Notifier\Integrations
 * @since 1.0.0
 */

namespace suspended_Order_Notifier\Integrations;

use suspended_Order_Notifier\Database\AbandonedCart as AbandonedCartModel;
use suspended_Order_Notifier\Notifications\Manager as NotificationManager;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Manages abandoned cart detection and notifications.
 *
 * @since 1.0.0
 */
class AbandonedCart {

    /**
     * Abandoned cart model.
     *
     * @var AbandonedCartModel
     */
    private $cart_model;

    /**
     * Notification manager.
     *
     * @var NotificationManager
     */
    private $notification_manager;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->cart_model           = new AbandonedCartModel();
        $this->notification_manager = new NotificationManager();

        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     *
     * @return void
     */
    private function init_hooks() {
        // Cron job to check for abandoned carts.
        add_action( 'won_check_abandoned_carts', array( $this, 'check_abandoned_carts' ) );

        // Track checkout field changes via AJAX.
        add_action( 'wp_ajax_won_track_checkout_field', array( $this, 'ajax_track_checkout_field' ) );
        add_action( 'wp_ajax_nopriv_won_track_checkout_field', array( $this, 'ajax_track_checkout_field' ) );

        // Enqueue frontend scripts for cart tracking.
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_tracking_scripts' ) );
    }

    /**
     * Check for abandoned carts and send notifications.
     *
     * This runs on the cron schedule (every 5 minutes).
     *
     * @return void
     */
    public function check_abandoned_carts() {
        if ( 'yes' !== get_option( 'won_enable_abandoned_cart', 'yes' ) ) {
            return;
        }

        // First, mark carts as abandoned if inactive.
        $abandon_time = (int) get_option( 'won_abandoned_cart_delay', 60 );
        $this->cart_model->mark_abandoned( $abandon_time );

        // Get abandoned carts that haven't been notified.
        $abandoned_carts = $this->cart_model->get_abandoned( 0 );

        if ( empty( $abandoned_carts ) ) {
            return;
        }

        foreach ( $abandoned_carts as $cart ) {
            // Skip if no phone number available.
            if ( empty( $cart->customer_phone ) && empty( $cart->customer_email ) ) {
                // Mark as notified to prevent re-processing.
                $this->cart_model->mark_notified( $cart->id );
                continue;
            }

            $this->send_abandoned_cart_notification( $cart );
        }
    }

    /**
     * Send abandoned cart notification.
     *
     * @param object $cart Cart database row.
     * @return void
     */
    private function send_abandoned_cart_notification( $cart ) {
        $cart_items = json_decode( $cart->cart_contents, true );

        if ( empty( $cart_items ) ) {
            $this->cart_model->mark_notified( $cart->id );
            return;
        }

        // Build items string.
        $items_list = array();
        foreach ( $cart_items as $item ) {
            $items_list[] = sprintf(
                '• %s x%d — %s',
                $item['name'],
                $item['quantity'],
                wc_price( $item['total'], array( 'currency' => $cart->currency ) )
            );
        }

        $data = array(
            'customer_name'  => $cart->customer_name ?: __( 'Customer', 'suspended-order-notifier' ),
            'customer_email' => $cart->customer_email ?: '',
            'customer_phone' => $cart->customer_phone ?: '',
            'cart_items'     => implode( "\n", $items_list ),
            'cart_total'     => wc_price( $cart->cart_total, array( 'currency' => $cart->currency ) ),
            'items_count'    => count( $cart_items ),
            'abandoned_time' => $cart->abandoned_at,
            'cart_url'       => wc_get_cart_url(),
            'checkout_url'   => wc_get_checkout_url(),
        );

        // Use the store owner's phone for notification (admin alert about abandoned cart).
        $this->notification_manager->queue_notification(
            'abandoned_cart',
            $cart->id,
            $data
        );

        // Mark as notified.
        $this->cart_model->mark_notified( $cart->id );

        /**
         * Action fired after abandoned cart notification is sent.
         *
         * @param object $cart Cart data.
         * @param array  $data Template data.
         */
        do_action( 'won_after_abandoned_cart_notification', $cart, $data );
    }

    /**
     * AJAX handler for tracking checkout field changes.
     *
     * Captures customer info (email, phone) during checkout before order is placed.
     *
     * @return void
     */
    public function ajax_track_checkout_field() {
        // Verify nonce.
        if ( ! check_ajax_referer( 'won_cart_tracking', 'nonce', false ) ) {
            wp_send_json_error( 'Invalid nonce' );
        }

        $field = sanitize_text_field( wp_unslash( $_POST['field'] ?? '' ) );
        $value = sanitize_text_field( wp_unslash( $_POST['value'] ?? '' ) );

        if ( empty( $field ) || empty( $value ) ) {
            wp_send_json_error( 'Missing data' );
        }

        // Get session ID.
        if ( ! function_exists( 'WC' ) || is_null( WC()->session ) ) {
            wp_send_json_error( 'No session' );
        }

        $session_id = WC()->session->get_customer_id();
        if ( ! $session_id ) {
            wp_send_json_error( 'No session ID' );
        }

        // Update the cart record with customer info.
        $cart = $this->cart_model->get_by_session( $session_id );
        if ( ! $cart ) {
            wp_send_json_error( 'Cart not found' );
        }

        $update_data = array( 'session_id' => $session_id );

        switch ( $field ) {
            case 'billing_email':
                $update_data['customer_email'] = sanitize_email( $value );
                break;
            case 'billing_phone':
                $update_data['customer_phone'] = sanitize_text_field( $value );
                break;
            case 'billing_first_name':
            case 'billing_last_name':
                $first = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
                $last  = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
                $update_data['customer_name'] = trim( $first . ' ' . $last );
                break;
        }

        $this->cart_model->upsert( $update_data );

        wp_send_json_success();
    }

    /**
     * Enqueue frontend scripts for cart tracking.
     *
     * @return void
     */
    public function enqueue_tracking_scripts() {
        if ( 'yes' !== get_option( 'won_enable_abandoned_cart', 'yes' ) ) {
            return;
        }

        // Only load on checkout page.
        if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
            return;
        }

        wp_enqueue_script(
            'won-cart-tracking',
            WON_PLUGIN_URL . 'assets/js/cart-tracking.js',
            array( 'jquery' ),
            WON_VERSION,
            true
        );

        wp_localize_script(
            'won-cart-tracking',
            'wonCartTracking',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'won_cart_tracking' ),
            )
        );
    }
}
