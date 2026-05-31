<?php
/**
 * Plugin Name: WhatsApp Order Notifier
 * Plugin URI: https://whatsapp-order-notifier.com
 * Description: Instant WhatsApp alerts for WooCommerce orders, low stock, refunds & abandoned carts. Uses free self-hosted backend on Hugging Face.
 * Version: 2.0.0
 * Author: WhatsApp Order Notifier
 * Author URI: https://whatsapp-order-notifier.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: won-notifier
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 *
 * @package suspended_Order_Notifier
 */

namespace suspended_Order_Notifier;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WON_VERSION', '2.0.0' );
define( 'WON_PLUGIN_FILE', __FILE__ );
define( 'WON_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WON_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WON_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class.
 */
final class Plugin {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies() {
        require_once WON_PLUGIN_DIR . 'includes/Autoloader.php';
        Autoloader::register();
    }

    private function init_hooks() {
        register_activation_hook( WON_PLUGIN_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( WON_PLUGIN_FILE, array( $this, 'deactivate' ) );

        add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ), 20 );
        add_action( 'admin_init', array( $this, 'admin_init' ) );
        add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
    }

    public function activate() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            deactivate_plugins( WON_PLUGIN_BASENAME );
            wp_die( esc_html__( 'WhatsApp Order Notifier requires WooCommerce.', 'won-notifier' ), 'Plugin Activation Error', array( 'back_link' => true ) );
        }

        $installer = new Database\Installer();
        $installer->install();
        $this->set_defaults();
        $this->schedule_events();

        set_transient( 'won_activation_redirect', true, 30 );
        flush_rewrite_rules();
    }

    public function deactivate() {
        wp_clear_scheduled_hook( 'won_process_queue' );
        wp_clear_scheduled_hook( 'won_check_abandoned_carts' );
        wp_clear_scheduled_hook( 'won_daily_cleanup' );
        flush_rewrite_rules();
    }

    public function on_plugins_loaded() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', function () {
                echo '<div class="notice notice-error"><p>' . esc_html__( 'WhatsApp Order Notifier requires WooCommerce.', 'won-notifier' ) . '</p></div>';
            } );
            return;
        }

        load_plugin_textdomain( 'won-notifier', false, dirname( WON_PLUGIN_BASENAME ) . '/languages' );

        new Admin\Dashboard();
        new Admin\Settings();
        new Api\RestController();
        new Integrations\WooCommerce();
        new Integrations\AbandonedCart();
        new Queue\Processor();
    }

    public function admin_init() {
        if ( get_transient( 'won_activation_redirect' ) ) {
            delete_transient( 'won_activation_redirect' );
            if ( ! isset( $_GET['activate-multi'] ) ) {
                wp_safe_redirect( admin_url( 'admin.php?page=won-dashboard' ) );
                exit;
            }
        }
    }

    public function declare_hpos_compatibility() {
        if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WON_PLUGIN_FILE, true );
        }
    }

    private function set_defaults() {
        $defaults = array(
            'won_hf_space_url'             => '',
            'won_api_secret'               => '',
            'won_phone_number'             => '',
            'won_country_code'             => '+91',
            'won_enable_order_alerts'      => 'yes',
            'won_enable_stock_alerts'      => 'yes',
            'won_enable_refund_alerts'     => 'yes',
            'won_enable_abandoned_cart'    => 'yes',
            'won_abandoned_cart_delay'     => 60,
            'won_stock_threshold'          => 5,
            'won_queue_batch_size'         => 10,
            'won_retry_attempts'           => 3,
            'won_log_retention_days'       => 30,
            'won_dark_mode'                => 'auto',
            'won_template_order'           => "🛒 *New Order #{order_id}*\n\n👤 {customer_name}\n📱 {customer_phone}\n📧 {customer_email}\n\n📦 Items:\n{order_items}\n\n💰 Total: {order_total}\n💳 Payment: {payment_method}\n📍 {shipping_address}\n\n🕐 {order_date}",
            'won_template_stock'           => "⚠️ *{stock_status}*\n\n📦 {product_name}\n🔢 Stock: {stock_quantity}\n🆔 SKU: {product_sku}\n\n⏰ {alert_time}",
            'won_template_refund'          => "🔄 *Refund Request*\n\n🛒 Order #{order_id}\n👤 {customer_name}\n💰 Amount: {refund_amount}\n📝 Reason: {refund_reason}\n\n⏰ {refund_date}",
            'won_template_abandoned_cart'   => "🛒 *Abandoned Cart*\n\n👤 {customer_name}\n📧 {customer_email}\n\n📦 Items:\n{cart_items}\n\n💰 Value: {cart_total}\n⏰ Abandoned: {abandoned_time}",
        );

        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( $key ) ) {
                update_option( $key, $value );
            }
        }
    }

    private function schedule_events() {
        if ( ! wp_next_scheduled( 'won_process_queue' ) ) {
            wp_schedule_event( time(), 'every_minute', 'won_process_queue' );
        }
        if ( ! wp_next_scheduled( 'won_check_abandoned_carts' ) ) {
            wp_schedule_event( time(), 'every_five_minutes', 'won_check_abandoned_carts' );
        }
        if ( ! wp_next_scheduled( 'won_daily_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'won_daily_cleanup' );
        }
    }
}

// Custom cron schedules.
add_filter( 'cron_schedules', function ( $schedules ) {
    $schedules['every_minute'] = array( 'interval' => 60, 'display' => 'Every Minute' );
    $schedules['every_five_minutes'] = array( 'interval' => 300, 'display' => 'Every 5 Minutes' );
    return $schedules;
} );

// Boot.
Plugin::instance();
