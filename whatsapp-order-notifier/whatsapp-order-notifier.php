<?php
/**
 * Plugin Name: WhatsApp Order Notifier
 * Plugin URI: https://whatsapp-order-notifier.com
 * Description: Instant WhatsApp alerts for WooCommerce orders, low stock, refunds, and abandoned carts.
 * Version: 1.0.0
 * Author: WhatsApp Order Notifier
 * Author URI: https://whatsapp-order-notifier.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: suspended-order-notifier
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 *
 * @package suspended_Order_Notifier
 */

namespace suspended_Order_Notifier;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants.
define( 'WON_VERSION', '1.0.0' );
define( 'WON_PLUGIN_FILE', __FILE__ );
define( 'WON_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WON_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WON_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'WON_DB_VERSION', '1.0.0' );

/**
 * Main plugin class - Singleton pattern.
 *
 * @since 1.0.0
 */
final class Plugin {

    /**
     * Single instance of the class.
     *
     * @var Plugin|null
     */
    private static $instance = null;

    /**
     * Plugin components.
     *
     * @var array
     */
    private $components = array();

    /**
     * Get single instance of the class.
     *
     * @return Plugin
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct() {
        $this->check_requirements();
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Prevent cloning.
     */
    private function __clone() {}

    /**
     * Prevent unserialization.
     */
    public function __wakeup() {
        throw new \Exception( 'Cannot unserialize singleton' );
    }

    /**
     * Check plugin requirements.
     *
     * @return void
     */
    private function check_requirements() {
        if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
            add_action( 'admin_notices', array( $this, 'php_version_notice' ) );
            return;
        }
    }

    /**
     * Load plugin dependencies.
     *
     * @return void
     */
    private function load_dependencies() {
        require_once WON_PLUGIN_DIR . 'includes/Autoloader.php';
        Autoloader::register();
    }

    /**
     * Initialize hooks.
     *
     * @return void
     */
    private function init_hooks() {
        register_activation_hook( WON_PLUGIN_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( WON_PLUGIN_FILE, array( $this, 'deactivate' ) );

        add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ), 20 );
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'admin_init', array( $this, 'admin_init' ) );

        // Declare HPOS compatibility.
        add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
    }

    /**
     * Plugin activation.
     *
     * @return void
     */
    public function activate() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            deactivate_plugins( WON_PLUGIN_BASENAME );
            wp_die(
                esc_html__( 'WhatsApp Order Notifier requires WooCommerce to be installed and active.', 'suspended-order-notifier' ),
                'Plugin Activation Error',
                array( 'back_link' => true )
            );
        }

        // Run database migrations.
        $installer = new Database\Installer();
        $installer->install();

        // Set default options.
        $this->set_default_options();

        // Schedule cron events.
        $this->schedule_events();

        // Set activation flag.
        set_transient( 'won_activation_redirect', true, 30 );

        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation.
     *
     * @return void
     */
    public function deactivate() {
        // Clear scheduled events.
        wp_clear_scheduled_hook( 'won_process_queue' );
        wp_clear_scheduled_hook( 'won_check_abandoned_carts' );
        wp_clear_scheduled_hook( 'won_daily_cleanup' );

        flush_rewrite_rules();
    }

    /**
     * Run on plugins_loaded hook.
     *
     * @return void
     */
    public function on_plugins_loaded() {
        // Check WooCommerce is active.
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            return;
        }

        // Load text domain.
        load_plugin_textdomain( 'suspended-order-notifier', false, dirname( WON_PLUGIN_BASENAME ) . '/languages' );

        // Initialize components.
        $this->init_components();
    }

    /**
     * Run on init hook.
     *
     * @return void
     */
    public function init() {
        // Register custom post statuses if needed.
    }

    /**
     * Run on admin_init hook.
     *
     * @return void
     */
    public function admin_init() {
        // Activation redirect.
        if ( get_transient( 'won_activation_redirect' ) ) {
            delete_transient( 'won_activation_redirect' );
            if ( ! isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
                wp_safe_redirect( admin_url( 'admin.php?page=won-dashboard' ) );
                exit;
            }
        }
    }

    /**
     * Initialize plugin components.
     *
     * @return void
     */
    private function init_components() {
        $this->components['settings']       = new Admin\Settings();
        $this->components['dashboard']      = new Admin\Dashboard();
        $this->components['notifications']  = new Notifications\Manager();
        $this->components['queue']          = new Queue\Processor();
        $this->components['woocommerce']    = new Integrations\WooCommerce();
        $this->components['rest_api']       = new Api\RestController();
        $this->components['abandoned_cart'] = new Integrations\AbandonedCart();
    }

    /**
     * Get a plugin component.
     *
     * @param string $component Component name.
     * @return object|null
     */
    public function get_component( $component ) {
        return isset( $this->components[ $component ] ) ? $this->components[ $component ] : null;
    }

    /**
     * Set default plugin options.
     *
     * @return void
     */
    private function set_default_options() {
        $defaults = array(
            'won_api_provider'          => 'whatsapp_business',
            'won_phone_number'          => '',
            'won_country_code'          => '+91',
            'won_enable_order_alerts'   => 'yes',
            'won_enable_stock_alerts'   => 'yes',
            'won_enable_refund_alerts'  => 'yes',
            'won_enable_abandoned_cart' => 'yes',
            'won_abandoned_cart_delay'  => 60,
            'won_stock_threshold'       => 5,
            'won_message_template_order' => "🛒 *New Order #{order_id}*\n\n👤 {customer_name}\n📱 {customer_phone}\n📧 {customer_email}\n\n📦 Items:\n{order_items}\n\n💰 Total: {order_total}\n💳 Payment: {payment_method}\n📍 Shipping: {shipping_address}\n\n🕐 {order_date}",
            'won_message_template_stock' => "⚠️ *Low Stock Alert*\n\n📦 {product_name}\n🔢 Stock: {stock_quantity}\n🆔 SKU: {product_sku}\n\n⏰ {alert_time}",
            'won_message_template_refund' => "🔄 *Refund Request*\n\n🛒 Order #{order_id}\n👤 {customer_name}\n💰 Amount: {refund_amount}\n📝 Reason: {refund_reason}\n\n⏰ {refund_date}",
            'won_message_template_abandoned' => "🛒 *Abandoned Cart Alert*\n\n👤 {customer_name}\n📧 {customer_email}\n\n📦 Items:\n{cart_items}\n\n💰 Cart Value: {cart_total}\n⏰ Abandoned: {abandoned_time}",
            'won_queue_batch_size'      => 10,
            'won_retry_attempts'        => 3,
            'won_retry_delay'           => 300,
            'won_log_retention_days'    => 30,
            'won_dark_mode'             => 'auto',
        );

        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( $key ) ) {
                update_option( $key, $value );
            }
        }
    }

    /**
     * Schedule cron events.
     *
     * @return void
     */
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

    /**
     * Declare High-Performance Order Storage compatibility.
     *
     * @return void
     */
    public function declare_hpos_compatibility() {
        if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WON_PLUGIN_FILE, true );
        }
    }

    /**
     * Display PHP version notice.
     *
     * @return void
     */
    public function php_version_notice() {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__( 'WhatsApp Order Notifier requires PHP 7.4 or higher. Please upgrade your PHP version.', 'suspended-order-notifier' );
        echo '</p></div>';
    }

    /**
     * Display WooCommerce missing notice.
     *
     * @return void
     */
    public function woocommerce_missing_notice() {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__( 'WhatsApp Order Notifier requires WooCommerce to be installed and active.', 'suspended-order-notifier' );
        echo '</p></div>';
    }
}

/**
 * Add custom cron schedules.
 *
 * @param array $schedules Existing schedules.
 * @return array
 */
function won_add_cron_schedules( $schedules ) {
    $schedules['every_minute'] = array(
        'interval' => 60,
        'display'  => esc_html__( 'Every Minute', 'suspended-order-notifier' ),
    );
    $schedules['every_five_minutes'] = array(
        'interval' => 300,
        'display'  => esc_html__( 'Every Five Minutes', 'suspended-order-notifier' ),
    );
    return $schedules;
}
add_filter( 'cron_schedules', __NAMESPACE__ . '\\won_add_cron_schedules' );

/**
 * Initialize the plugin.
 *
 * @return Plugin
 */
function won() {
    return Plugin::instance();
}

// Initialize.
won();
