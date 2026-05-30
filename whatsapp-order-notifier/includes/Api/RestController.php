<?php
/**
 * REST API Controller.
 *
 * @package suspended_Order_Notifier\Api
 * @since 1.0.0
 */

namespace suspended_Order_Notifier\Api;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers and handles REST API endpoints.
 *
 * @since 1.0.0
 */
class RestController {

    /**
     * Namespace for REST routes.
     *
     * @var string
     */
    private $namespace = 'won/v1';

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register REST API routes.
     *
     * @return void
     */
    public function register_routes() {
        // Dashboard stats.
        register_rest_route(
            $this->namespace,
            '/stats',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_stats' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
            )
        );

        // Chart data.
        register_rest_route(
            $this->namespace,
            '/stats/chart',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_chart_data' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
                'args'                => array(
                    'days' => array(
                        'default'           => 7,
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );

        // Notification logs.
        register_rest_route(
            $this->namespace,
            '/logs',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_logs' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
                'args'                => $this->get_logs_args(),
            )
        );

        // Send test message.
        register_rest_route(
            $this->namespace,
            '/test',
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'send_test_message' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
                'args'                => array(
                    'phone' => array(
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'message' => array(
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_textarea_field',
                    ),
                ),
            )
        );

        // Validate API credentials.
        register_rest_route(
            $this->namespace,
            '/validate',
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'validate_credentials' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
                'args'                => array(
                    'provider' => array(
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );

        // Save settings.
        register_rest_route(
            $this->namespace,
            '/settings',
            array(
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_settings' ),
                    'permission_callback' => array( $this, 'check_admin_permission' ),
                ),
                array(
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'save_settings' ),
                    'permission_callback' => array( $this, 'check_admin_permission' ),
                ),
            )
        );

        // Webhook endpoint (public).
        register_rest_route(
            $this->namespace,
            '/webhook',
            array(
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'webhook_verify' ),
                    'permission_callback' => '__return_true',
                ),
                array(
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'webhook_receive' ),
                    'permission_callback' => '__return_true',
                ),
            )
        );

        // Resend notification.
        register_rest_route(
            $this->namespace,
            '/resend/(?P<id>\d+)',
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'resend_notification' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
                'args'                => array(
                    'id' => array(
                        'required'          => true,
                        'validate_callback' => function ( $param ) {
                            return is_numeric( $param );
                        },
                    ),
                ),
            )
        );

        // Abandoned carts.
        register_rest_route(
            $this->namespace,
            '/abandoned-carts',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_abandoned_carts' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
            )
        );
    }

    /**
     * Check if current user has admin permissions.
     *
     * @return bool
     */
    public function check_admin_permission() {
        return current_user_can( 'manage_woocommerce' );
    }

    /**
     * Get dashboard statistics.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function get_stats( $request ) {
        $period = $request->get_param( 'period' ) ?? 'today';

        // Cache stats for 60 seconds to reduce DB load.
        $cache_key = 'won_stats_' . sanitize_key( $period );
        $data = get_transient( $cache_key );

        if ( false === $data ) {
            $log_model  = new \suspended_Order_Notifier\Database\NotificationLog();
            $cart_model = new \suspended_Order_Notifier\Database\AbandonedCart();
            $queue_model = new \suspended_Order_Notifier\Database\MessageQueue();

            $data = array(
                'notifications'   => $log_model->get_stats( $period ),
                'abandoned_carts' => $cart_model->get_stats(),
                'queue'           => $queue_model->get_stats(),
            );

            set_transient( $cache_key, $data, 60 );
        }

        return new \WP_REST_Response( $data, 200 );
    }

    /**
     * Get chart data.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function get_chart_data( $request ) {
        $log_model = new \suspended_Order_Notifier\Database\NotificationLog();
        $days = $request->get_param( 'days' ) ?? 7;

        $data = $log_model->get_chart_data( $days );

        return new \WP_REST_Response( $data, 200 );
    }

    /**
     * Get notification logs.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function get_logs( $request ) {
        $log_model = new \suspended_Order_Notifier\Database\NotificationLog();

        $args = array(
            'per_page'          => $request->get_param( 'per_page' ) ?? 20,
            'page'              => $request->get_param( 'page' ) ?? 1,
            'notification_type' => $request->get_param( 'type' ) ?? '',
            'status'            => $request->get_param( 'status' ) ?? '',
            'search'            => $request->get_param( 'search' ) ?? '',
            'date_from'         => $request->get_param( 'date_from' ) ?? '',
            'date_to'           => $request->get_param( 'date_to' ) ?? '',
        );

        $result = $log_model->get_logs( $args );

        return new \WP_REST_Response(
            array(
                'items'       => $result['items'],
                'total'       => $result['total'],
                'total_pages' => ceil( $result['total'] / $args['per_page'] ),
                'page'        => (int) $args['page'],
            ),
            200
        );
    }

    /**
     * Send a test message.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function send_test_message( $request ) {
        // Rate limit: max 5 test messages per minute.
        $user_id = get_current_user_id();
        $rate_key = 'won_test_rate_' . $user_id;
        $rate_count = (int) get_transient( $rate_key );
        if ( $rate_count >= 5 ) {
            return new \WP_REST_Response(
                array(
                    'success' => false,
                    'error'   => __( 'Rate limit exceeded. Please wait a minute before sending another test.', 'suspended-order-notifier' ),
                ),
                429
            );
        }
        set_transient( $rate_key, $rate_count + 1, MINUTE_IN_SECONDS );

        $phone = $request->get_param( 'phone' );
        $message = $request->get_param( 'message' );

        if ( empty( $message ) ) {
            $message = sprintf(
                "✅ *Test Message from WhatsApp Order Notifier*\n\nYour WhatsApp notifications are working correctly!\n\n🕐 %s\n🌐 %s",
                current_time( 'Y-m-d H:i:s' ),
                get_bloginfo( 'name' )
            );
        }

        $result = ProviderFactory::send( $phone, $message );

        if ( $result['success'] ) {
            // Log the test message.
            $log_model = new \suspended_Order_Notifier\Database\NotificationLog();
            $log_model->insert(
                array(
                    'notification_type'   => 'test',
                    'recipient_phone'     => $phone,
                    'message_content'     => $message,
                    'status'              => 'sent',
                    'provider_message_id' => $result['message_id'],
                    'sent_at'             => current_time( 'mysql' ),
                )
            );
        }

        return new \WP_REST_Response( $result, $result['success'] ? 200 : 400 );
    }

    /**
     * Validate API credentials.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function validate_credentials( $request ) {
        $provider_id = $request->get_param( 'provider' ) ?? '';
        $result = ProviderFactory::test_connection( $provider_id );

        return new \WP_REST_Response( $result, $result['success'] ? 200 : 400 );
    }

    /**
     * Get plugin settings.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function get_settings( $request ) {
        $settings = array(
            'api_provider'          => get_option( 'won_api_provider', 'whatsapp_business' ),
            'phone_number'          => get_option( 'won_phone_number', '' ),
            'country_code'          => get_option( 'won_country_code', '+91' ),
            'enable_order_alerts'   => get_option( 'won_enable_order_alerts', 'yes' ),
            'enable_stock_alerts'   => get_option( 'won_enable_stock_alerts', 'yes' ),
            'enable_refund_alerts'  => get_option( 'won_enable_refund_alerts', 'yes' ),
            'enable_abandoned_cart' => get_option( 'won_enable_abandoned_cart', 'yes' ),
            'abandoned_cart_delay'  => get_option( 'won_abandoned_cart_delay', 60 ),
            'stock_threshold'       => get_option( 'won_stock_threshold', 5 ),
            'queue_batch_size'      => get_option( 'won_queue_batch_size', 10 ),
            'retry_attempts'        => get_option( 'won_retry_attempts', 3 ),
            'retry_delay'           => get_option( 'won_retry_delay', 300 ),
            'log_retention_days'    => get_option( 'won_log_retention_days', 30 ),
            'dark_mode'             => get_option( 'won_dark_mode', 'auto' ),
            // Provider-specific.
            'whatsapp_access_token'        => ! empty( get_option( 'won_whatsapp_access_token' ) ) ? '••••••••' : '',
            'whatsapp_phone_number_id'     => get_option( 'won_whatsapp_phone_number_id', '' ),
            'whatsapp_business_account_id' => get_option( 'won_whatsapp_business_account_id', '' ),
            'twilio_account_sid'           => get_option( 'won_twilio_account_sid', '' ),
            'twilio_auth_token'            => ! empty( get_option( 'won_twilio_auth_token' ) ) ? '••••••••' : '',
            'twilio_from_number'           => get_option( 'won_twilio_from_number', '' ),
            // Templates.
            'message_template_order'     => get_option( 'won_message_template_order', '' ),
            'message_template_stock'     => get_option( 'won_message_template_stock', '' ),
            'message_template_refund'    => get_option( 'won_message_template_refund', '' ),
            'message_template_abandoned' => get_option( 'won_message_template_abandoned', '' ),
        );

        return new \WP_REST_Response( $settings, 200 );
    }

    /**
     * Save plugin settings.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function save_settings( $request ) {
        $params = $request->get_json_params();

        $allowed_settings = array(
            'won_api_provider',
            'won_phone_number',
            'won_country_code',
            'won_enable_order_alerts',
            'won_enable_stock_alerts',
            'won_enable_refund_alerts',
            'won_enable_abandoned_cart',
            'won_abandoned_cart_delay',
            'won_stock_threshold',
            'won_queue_batch_size',
            'won_retry_attempts',
            'won_retry_delay',
            'won_log_retention_days',
            'won_dark_mode',
            'won_whatsapp_access_token',
            'won_whatsapp_phone_number_id',
            'won_whatsapp_business_account_id',
            'won_whatsapp_webhook_verify_token',
            'won_twilio_account_sid',
            'won_twilio_auth_token',
            'won_twilio_from_number',
            'won_message_template_order',
            'won_message_template_stock',
            'won_message_template_refund',
            'won_message_template_abandoned',
        );

        $updated = array();

        foreach ( $params as $key => $value ) {
            $option_key = 'won_' . $key;

            // Skip masked values (password fields that weren't changed).
            if ( '••••••••' === $value ) {
                continue;
            }

            if ( in_array( $option_key, $allowed_settings, true ) ) {
                // Sanitize based on type.
                if ( in_array( $option_key, array( 'won_whatsapp_access_token', 'won_twilio_auth_token' ), true ) ) {
                    $value = sanitize_text_field( $value );
                } elseif ( strpos( $option_key, 'template' ) !== false ) {
                    $value = sanitize_textarea_field( $value );
                } elseif ( is_numeric( $value ) ) {
                    $value = absint( $value );
                } else {
                    $value = sanitize_text_field( $value );
                }

                update_option( $option_key, $value );
                $updated[] = $option_key;
            }
        }

        return new \WP_REST_Response(
            array(
                'success' => true,
                'message' => __( 'Settings saved successfully.', 'suspended-order-notifier' ),
                'updated' => $updated,
            ),
            200
        );
    }

    /**
     * Webhook verification (GET request from Meta).
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|string
     */
    public function webhook_verify( $request ) {
        $mode      = $request->get_param( 'hub_mode' );
        $token     = $request->get_param( 'hub_verify_token' );
        $challenge = $request->get_param( 'hub_challenge' );

        $verify_token = get_option( 'won_whatsapp_webhook_verify_token', '' );

        // Reject if no verify token is configured.
        if ( empty( $verify_token ) ) {
            return new \WP_REST_Response( 'Webhook not configured', 403 );
        }

        if ( 'subscribe' === $mode && hash_equals( $verify_token, $token ) ) {
            return new \WP_REST_Response( (int) $challenge, 200 );
        }

        return new \WP_REST_Response( 'Forbidden', 403 );
    }

    /**
     * Webhook receive (POST request for delivery status updates).
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function webhook_receive( $request ) {
        // Basic rate limiting for webhook endpoint.
        $rate_key = 'won_webhook_rate_' . md5( $_SERVER['REMOTE_ADDR'] ?? '' );
        $rate_count = (int) get_transient( $rate_key );
        if ( $rate_count > 100 ) {
            return new \WP_REST_Response( array( 'error' => 'Rate limit exceeded' ), 429 );
        }
        set_transient( $rate_key, $rate_count + 1, MINUTE_IN_SECONDS );

        $body = $request->get_json_params();

        // Process delivery status updates.
        if ( ! empty( $body['entry'] ) ) {
            foreach ( $body['entry'] as $entry ) {
                if ( ! empty( $entry['changes'] ) ) {
                    foreach ( $entry['changes'] as $change ) {
                        if ( ! empty( $change['value']['statuses'] ) ) {
                            $this->process_status_update( $change['value']['statuses'] );
                        }
                    }
                }
            }
        }

        return new \WP_REST_Response( array( 'status' => 'ok' ), 200 );
    }

    /**
     * Resend a failed notification.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function resend_notification( $request ) {
        $id = absint( $request->get_param( 'id' ) );

        $log_model = new \suspended_Order_Notifier\Database\NotificationLog();
        $log = $log_model->get( $id );

        if ( ! $log ) {
            return new \WP_REST_Response(
                array(
                    'success' => false,
                    'message' => __( 'Notification not found.', 'suspended-order-notifier' ),
                ),
                404
            );
        }

        // Queue for resend.
        $queue_model = new \suspended_Order_Notifier\Database\MessageQueue();
        $queue_model->enqueue(
            array(
                'notification_type' => $log->notification_type,
                'reference_id'      => $log->reference_id,
                'recipient_phone'   => $log->recipient_phone,
                'message_content'   => $log->message_content,
                'priority'          => 1, // High priority for resends.
            )
        );

        return new \WP_REST_Response(
            array(
                'success' => true,
                'message' => __( 'Notification queued for resending.', 'suspended-order-notifier' ),
            ),
            200
        );
    }

    /**
     * Get abandoned carts data.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function get_abandoned_carts( $request ) {
        $cart_model = new \suspended_Order_Notifier\Database\AbandonedCart();

        return new \WP_REST_Response(
            array(
                'stats' => $cart_model->get_stats(),
            ),
            200
        );
    }

    /**
     * Process delivery status updates from webhook.
     *
     * @param array $statuses Status updates.
     * @return void
     */
    private function process_status_update( $statuses ) {
        global $wpdb;

        $log_model = new \suspended_Order_Notifier\Database\NotificationLog();

        foreach ( $statuses as $status ) {
            if ( empty( $status['id'] ) ) {
                continue;
            }

            // Find the log entry by provider message ID.
            $log = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}won_notification_log WHERE provider_message_id = %s",
                    $status['id']
                )
            );

            if ( $log ) {
                $new_status = 'sent';
                if ( in_array( $status['status'], array( 'delivered', 'read' ), true ) ) {
                    $new_status = $status['status'];
                } elseif ( 'failed' === $status['status'] ) {
                    $new_status = 'failed';
                }

                $log_model->update(
                    $log->id,
                    array( 'status' => $new_status )
                );
            }
        }
    }

    /**
     * Get logs endpoint argument definitions.
     *
     * @return array
     */
    private function get_logs_args() {
        return array(
            'per_page' => array(
                'default'           => 20,
                'sanitize_callback' => 'absint',
            ),
            'page' => array(
                'default'           => 1,
                'sanitize_callback' => 'absint',
            ),
            'type' => array(
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'status' => array(
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'search' => array(
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'date_from' => array(
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'date_to' => array(
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
        );
    }
}
