<?php
/**
 * Admin Settings.
 *
 * @package suspended_Order_Notifier\Admin
 * @since 1.0.0
 */

namespace suspended_Order_Notifier\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles plugin settings management.
 *
 * @since 1.0.0
 */
class Settings {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_filter( 'plugin_action_links_' . WON_PLUGIN_BASENAME, array( $this, 'add_action_links' ) );
        add_filter( 'plugin_row_meta', array( $this, 'add_row_meta' ), 10, 2 );
    }

    /**
     * Register settings.
     *
     * @return void
     */
    public function register_settings() {
        // General settings.
        register_setting( 'won_general_settings', 'won_api_provider', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'whatsapp_business',
        ) );

        register_setting( 'won_general_settings', 'won_phone_number', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );

        register_setting( 'won_general_settings', 'won_country_code', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '+91',
        ) );

        // Notification toggles.
        register_setting( 'won_notification_settings', 'won_enable_order_alerts', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'yes',
        ) );

        register_setting( 'won_notification_settings', 'won_enable_stock_alerts', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'yes',
        ) );

        register_setting( 'won_notification_settings', 'won_enable_refund_alerts', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'yes',
        ) );

        register_setting( 'won_notification_settings', 'won_enable_abandoned_cart', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'yes',
        ) );

        register_setting( 'won_notification_settings', 'won_abandoned_cart_delay', array(
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 60,
        ) );

        register_setting( 'won_notification_settings', 'won_stock_threshold', array(
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 5,
        ) );

        // Advanced settings.
        register_setting( 'won_advanced_settings', 'won_queue_batch_size', array(
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 10,
        ) );

        register_setting( 'won_advanced_settings', 'won_retry_attempts', array(
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 3,
        ) );

        register_setting( 'won_advanced_settings', 'won_retry_delay', array(
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 300,
        ) );

        register_setting( 'won_advanced_settings', 'won_log_retention_days', array(
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 30,
        ) );

        register_setting( 'won_advanced_settings', 'won_dark_mode', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'auto',
        ) );
    }

    /**
     * Add plugin action links.
     *
     * @param array $links Existing links.
     * @return array
     */
    public function add_action_links( $links ) {
        $plugin_links = array(
            '<a href="' . admin_url( 'admin.php?page=won-settings' ) . '">' . __( 'Settings', 'suspended-order-notifier' ) . '</a>',
            '<a href="' . admin_url( 'admin.php?page=won-dashboard' ) . '">' . __( 'Dashboard', 'suspended-order-notifier' ) . '</a>',
        );

        return array_merge( $plugin_links, $links );
    }

    /**
     * Add plugin row meta links.
     *
     * @param array  $links Plugin row meta.
     * @param string $file  Plugin file.
     * @return array
     */
    public function add_row_meta( $links, $file ) {
        if ( WON_PLUGIN_BASENAME === $file ) {
            $links[] = '<a href="https://whatsapp-order-notifier.com/docs" target="_blank">' . __( 'Documentation', 'suspended-order-notifier' ) . '</a>';
            $links[] = '<a href="https://whatsapp-order-notifier.com/support" target="_blank">' . __( 'Support', 'suspended-order-notifier' ) . '</a>';
        }

        return $links;
    }

    /**
     * Get all settings grouped.
     *
     * @return array
     */
    public static function get_all_settings() {
        return array(
            'general' => array(
                'api_provider' => get_option( 'won_api_provider', 'whatsapp_business' ),
                'phone_number' => get_option( 'won_phone_number', '' ),
                'country_code' => get_option( 'won_country_code', '+91' ),
            ),
            'notifications' => array(
                'enable_order_alerts'   => get_option( 'won_enable_order_alerts', 'yes' ),
                'enable_stock_alerts'   => get_option( 'won_enable_stock_alerts', 'yes' ),
                'enable_refund_alerts'  => get_option( 'won_enable_refund_alerts', 'yes' ),
                'enable_abandoned_cart' => get_option( 'won_enable_abandoned_cart', 'yes' ),
                'abandoned_cart_delay'  => get_option( 'won_abandoned_cart_delay', 60 ),
                'stock_threshold'       => get_option( 'won_stock_threshold', 5 ),
            ),
            'advanced' => array(
                'queue_batch_size'   => get_option( 'won_queue_batch_size', 10 ),
                'retry_attempts'     => get_option( 'won_retry_attempts', 3 ),
                'retry_delay'        => get_option( 'won_retry_delay', 300 ),
                'log_retention_days' => get_option( 'won_log_retention_days', 30 ),
                'dark_mode'          => get_option( 'won_dark_mode', 'auto' ),
            ),
        );
    }
}
