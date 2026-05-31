<?php
/**
 * REST API Controller - Plugin endpoints for admin dashboard.
 *
 * @package suspended_Order_Notifier\Api
 */

namespace suspended_Order_Notifier\Api;

if ( ! defined( 'ABSPATH' ) ) exit;

class RestController {

    private $namespace = 'won/v1';

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route( $this->namespace, '/status', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_whatsapp_status' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( $this->namespace, '/qr', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_qr_code' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( $this->namespace, '/send-test', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'send_test' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( $this->namespace, '/logout', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'logout_whatsapp' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( $this->namespace, '/stats', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_stats' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( $this->namespace, '/logs', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_logs' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( $this->namespace, '/settings', array(
            array( 'methods' => 'GET', 'callback' => array( $this, 'get_settings' ), 'permission_callback' => array( $this, 'check_permission' ) ),
            array( 'methods' => 'POST', 'callback' => array( $this, 'save_settings' ), 'permission_callback' => array( $this, 'check_permission' ) ),
        ) );

        register_rest_route( $this->namespace, '/resend/(?P<id>\d+)', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'resend_notification' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );
    }

    public function check_permission() {
        return current_user_can( 'manage_woocommerce' );
    }

    /**
     * GET /status - WhatsApp connection status from HF backend.
     */
    public function get_whatsapp_status() {
        $api = new HFWhatsApp();
        $result = $api->get_status();
        return new \WP_REST_Response( $result, 200 );
    }

    /**
     * GET /qr - Get QR code for WhatsApp auth.
     */
    public function get_qr_code() {
        $api = new HFWhatsApp();
        $result = $api->get_qr();
        return new \WP_REST_Response( $result, 200 );
    }

    /**
     * POST /send-test - Send a test WhatsApp message.
     */
    public function send_test( $request ) {
        $phone = sanitize_text_field( $request->get_param( 'phone' ) );
        if ( empty( $phone ) ) {
            return new \WP_REST_Response( array( 'success' => false, 'error' => 'Phone number required.' ), 400 );
        }

        $message = sprintf(
            "✅ *WhatsApp Order Notifier*\n\nTest successful! Your notifications are working.\n\n🌐 %s\n🕐 %s",
            get_bloginfo( 'name' ),
            current_time( 'Y-m-d H:i:s' )
        );

        $api = new HFWhatsApp();
        $result = $api->send_message( $phone, $message );

        // Log it
        if ( $result['success'] ) {
            $log = new \suspended_Order_Notifier\Database\NotificationLog();
            $log->insert( array(
                'notification_type' => 'test',
                'recipient_phone'   => $phone,
                'message_content'   => $message,
                'status'            => 'sent',
                'provider_message_id' => $result['messageId'] ?? '',
                'sent_at'           => current_time( 'mysql' ),
            ) );
        }

        return new \WP_REST_Response( $result, $result['success'] ? 200 : 400 );
    }

    /**
     * POST /logout - Disconnect WhatsApp.
     */
    public function logout_whatsapp() {
        $api = new HFWhatsApp();
        $result = $api->logout();
        return new \WP_REST_Response( $result, 200 );
    }

    /**
     * GET /stats - Dashboard statistics.
     */
    public function get_stats( $request ) {
        $period = sanitize_text_field( $request->get_param( 'period' ) ?? 'today' );

        $cache_key = 'won_stats_' . $period;
        $data = get_transient( $cache_key );

        if ( false === $data ) {
            $log = new \suspended_Order_Notifier\Database\NotificationLog();
            $queue = new \suspended_Order_Notifier\Database\MessageQueue();
            $cart = new \suspended_Order_Notifier\Database\AbandonedCart();

            $data = array(
                'notifications'   => $log->get_stats( $period ),
                'queue'           => $queue->get_stats(),
                'abandoned_carts' => $cart->get_stats(),
            );
            set_transient( $cache_key, $data, 60 );
        }

        return new \WP_REST_Response( $data, 200 );
    }

    /**
     * GET /logs - Notification history.
     */
    public function get_logs( $request ) {
        $log = new \suspended_Order_Notifier\Database\NotificationLog();
        $result = $log->get_logs( array(
            'per_page' => absint( $request->get_param( 'per_page' ) ?? 20 ),
            'page'     => absint( $request->get_param( 'page' ) ?? 1 ),
            'notification_type' => sanitize_text_field( $request->get_param( 'type' ) ?? '' ),
            'status'   => sanitize_text_field( $request->get_param( 'status' ) ?? '' ),
            'search'   => sanitize_text_field( $request->get_param( 'search' ) ?? '' ),
        ) );

        return new \WP_REST_Response( array(
            'items'       => $result['items'],
            'total'       => $result['total'],
            'total_pages' => ceil( $result['total'] / max( 1, absint( $request->get_param( 'per_page' ) ?? 20 ) ) ),
        ), 200 );
    }

    /**
     * GET/POST /settings
     */
    public function get_settings() {
        return new \WP_REST_Response( array(
            'hf_space_url'           => get_option( 'won_hf_space_url', '' ),
            'api_secret'             => get_option( 'won_api_secret' ) ? '••••••••' : '',
            'phone_number'           => get_option( 'won_phone_number', '' ),
            'country_code'           => get_option( 'won_country_code', '+91' ),
            'enable_order_alerts'    => get_option( 'won_enable_order_alerts', 'yes' ),
            'enable_stock_alerts'    => get_option( 'won_enable_stock_alerts', 'yes' ),
            'enable_refund_alerts'   => get_option( 'won_enable_refund_alerts', 'yes' ),
            'enable_abandoned_cart'  => get_option( 'won_enable_abandoned_cart', 'yes' ),
            'abandoned_cart_delay'   => get_option( 'won_abandoned_cart_delay', 60 ),
            'stock_threshold'        => get_option( 'won_stock_threshold', 5 ),
            'dark_mode'              => get_option( 'won_dark_mode', 'auto' ),
            'template_order'         => get_option( 'won_template_order', '' ),
            'template_stock'         => get_option( 'won_template_stock', '' ),
            'template_refund'        => get_option( 'won_template_refund', '' ),
            'template_abandoned_cart' => get_option( 'won_template_abandoned_cart', '' ),
        ), 200 );
    }

    public function save_settings( $request ) {
        $params = $request->get_json_params();

        $allowed = array(
            'won_hf_space_url', 'won_api_secret', 'won_phone_number', 'won_country_code',
            'won_enable_order_alerts', 'won_enable_stock_alerts', 'won_enable_refund_alerts',
            'won_enable_abandoned_cart', 'won_abandoned_cart_delay', 'won_stock_threshold',
            'won_dark_mode', 'won_template_order', 'won_template_stock',
            'won_template_refund', 'won_template_abandoned_cart',
            'won_queue_batch_size', 'won_retry_attempts', 'won_log_retention_days',
        );

        $updated = array();
        foreach ( $params as $key => $value ) {
            $option_key = strpos( $key, 'won_' ) === 0 ? $key : 'won_' . $key;
            if ( '••••••••' === $value ) continue;
            if ( ! in_array( $option_key, $allowed, true ) ) continue;

            if ( strpos( $option_key, 'template' ) !== false ) {
                $value = sanitize_textarea_field( $value );
            } elseif ( is_numeric( $value ) ) {
                $value = absint( $value );
            } else {
                $value = sanitize_text_field( $value );
            }

            update_option( $option_key, $value );
            $updated[] = $option_key;
        }

        // Clear stats cache
        delete_transient( 'won_stats_today' );
        delete_transient( 'won_stats_week' );

        return new \WP_REST_Response( array( 'success' => true, 'updated' => $updated ), 200 );
    }

    /**
     * POST /resend/{id} - Resend a failed notification.
     */
    public function resend_notification( $request ) {
        $id = absint( $request->get_param( 'id' ) );
        $log = new \suspended_Order_Notifier\Database\NotificationLog();
        $entry = $log->get( $id );

        if ( ! $entry ) {
            return new \WP_REST_Response( array( 'success' => false, 'error' => 'Not found.' ), 404 );
        }

        $queue = new \suspended_Order_Notifier\Database\MessageQueue();
        $queue->enqueue( array(
            'notification_type' => $entry->notification_type,
            'reference_id'      => $entry->reference_id,
            'recipient_phone'   => $entry->recipient_phone,
            'message_content'   => $entry->message_content,
            'priority'          => 1,
        ) );

        return new \WP_REST_Response( array( 'success' => true, 'message' => 'Queued for resend.' ), 200 );
    }
}
