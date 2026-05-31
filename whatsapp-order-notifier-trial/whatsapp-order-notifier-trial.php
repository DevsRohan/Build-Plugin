<?php
/**
 * Plugin Name: WhatsApp Order Notifier — Trial
 * Plugin URI: https://devsarun.io/plugin/whatsapp/
 * Description: 24-Hour Free Trial — Instant WhatsApp alerts for WooCommerce orders, stock, refunds & abandoned carts.
 * Version: 2.0.0-trial
 * Author: DevSarun
 * Author URI: https://devsarun.io/
 * License: GPL v2 or later
 * Text Domain: won-trial
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 *
 * @package WON_Trial
 */

namespace WON_Trial;

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'WON_TRIAL_VERSION', '2.0.0-trial' );
define( 'WON_TRIAL_FILE', __FILE__ );
define( 'WON_TRIAL_DIR', plugin_dir_path( __FILE__ ) );
define( 'WON_TRIAL_URL', plugin_dir_url( __FILE__ ) );
define( 'WON_TRIAL_BASENAME', plugin_basename( __FILE__ ) );
define( 'WON_TRIAL_HOURS', 24 );
define( 'WON_UPGRADE_URL', 'https://devsarun.io/plugin/whatsapp/' );

/**
 * Trial Manager — Handles activation time, expiry check, auto-delete.
 */
final class Trial_Manager {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Record activation time on first load
        $this->maybe_record_activation();

        // Check if trial expired
        if ( $this->is_expired() ) {
            add_action( 'admin_init', array( $this, 'handle_expiry' ) );
            add_action( 'admin_notices', array( $this, 'expired_notice' ) );
            return; // Don't load plugin features
        }

        // Trial is active — load everything
        $this->load_plugin();
    }

    /**
     * Record activation timestamp (only once, on first activation).
     */
    private function maybe_record_activation() {
        if ( ! get_option( 'won_trial_activated_at' ) ) {
            update_option( 'won_trial_activated_at', time() );
        }
    }

    /**
     * Check if the 24-hour trial has expired.
     */
    public function is_expired() {
        $activated = (int) get_option( 'won_trial_activated_at', 0 );
        if ( ! $activated ) return false;
        $expires_at = $activated + ( WON_TRIAL_HOURS * 3600 );
        return time() >= $expires_at;
    }

    /**
     * Get remaining seconds in trial.
     */
    public function get_remaining_seconds() {
        $activated = (int) get_option( 'won_trial_activated_at', 0 );
        if ( ! $activated ) return WON_TRIAL_HOURS * 3600;
        $expires_at = $activated + ( WON_TRIAL_HOURS * 3600 );
        $remaining = $expires_at - time();
        return max( 0, $remaining );
    }

    /**
     * Get expiry timestamp.
     */
    public function get_expiry_time() {
        $activated = (int) get_option( 'won_trial_activated_at', 0 );
        return $activated + ( WON_TRIAL_HOURS * 3600 );
    }

    /**
     * Handle trial expiry — auto-delete plugin.
     */
    public function handle_expiry() {
        // Only attempt self-delete once
        if ( get_option( 'won_trial_deletion_attempted' ) ) return;
        update_option( 'won_trial_deletion_attempted', 1 );

        // Deactivate self
        deactivate_plugins( WON_TRIAL_BASENAME );

        // Clean up options
        delete_option( 'won_trial_activated_at' );
        delete_option( 'won_hf_space_url' );
        delete_option( 'won_api_secret' );
        delete_option( 'won_phone_number' );
        delete_option( 'won_country_code' );
        delete_option( 'won_enable_order_alerts' );
        delete_option( 'won_enable_stock_alerts' );
        delete_option( 'won_enable_refund_alerts' );
        delete_option( 'won_enable_abandoned_cart' );
        delete_option( 'won_dark_mode' );

        // Drop tables
        global $wpdb;
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}won_notification_log" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}won_message_queue" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}won_abandoned_carts" );

        // Schedule self-deletion of files (runs after redirect)
        wp_schedule_single_event( time() + 5, 'won_trial_delete_files' );

        // Redirect to plugins page
        wp_safe_redirect( admin_url( 'plugins.php?won_trial_expired=1' ) );
        exit;
    }

    /**
     * Show expired notice.
     */
    public function expired_notice() {
        ?>
        <div class="notice notice-error" style="padding:20px;border-left-color:#EF4444;">
            <h3 style="margin:0 0 8px;color:#EF4444;">⏰ WhatsApp Order Notifier Trial Has Expired</h3>
            <p style="margin:0 0 12px;font-size:14px;">Your 24-hour free trial has ended. Upgrade to Pro for lifetime access — all features, unlimited notifications, free updates forever.</p>
            <a href="<?php echo esc_url( WON_UPGRADE_URL ); ?>" target="_blank" style="display:inline-block;padding:10px 24px;background:#25D366;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;font-size:14px;">🔥 Upgrade to Pro — $19</a>
            <span style="margin-left:12px;color:#666;font-size:13px;">One-time payment. No monthly fees.</span>
        </div>
        <?php
    }

    /**
     * Load the actual plugin functionality.
     */
    private function load_plugin() {
        require_once WON_TRIAL_DIR . 'includes/Autoloader.php';
        Autoloader::register();

        add_action( 'plugins_loaded', array( $this, 'init_plugin' ), 20 );
        add_action( 'admin_init', array( $this, 'maybe_redirect' ) );
        add_action( 'admin_notices', array( $this, 'trial_banner' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_trial_assets' ) );

        register_activation_hook( WON_TRIAL_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( WON_TRIAL_FILE, array( $this, 'deactivate' ) );

        add_action( 'before_woocommerce_init', function() {
            if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WON_TRIAL_FILE, true );
            }
        });

        // Custom cron schedules
        add_filter( 'cron_schedules', function( $s ) {
            $s['every_minute'] = array( 'interval' => 60, 'display' => 'Every Minute' );
            $s['every_five_minutes'] = array( 'interval' => 300, 'display' => 'Every 5 Minutes' );
            return $s;
        });
    }

    public function activate() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            deactivate_plugins( WON_TRIAL_BASENAME );
            wp_die( 'WhatsApp Order Notifier Trial requires WooCommerce.', 'Error', array( 'back_link' => true ) );
        }
        $installer = new Database\Installer();
        $installer->install();
        $this->set_defaults();
        $this->schedule_events();
        set_transient( 'won_trial_redirect', true, 30 );
    }

    public function deactivate() {
        wp_clear_scheduled_hook( 'won_process_queue' );
        wp_clear_scheduled_hook( 'won_check_abandoned_carts' );
        wp_clear_scheduled_hook( 'won_daily_cleanup' );
    }

    public function init_plugin() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', function() {
                echo '<div class="notice notice-error"><p>WhatsApp Order Notifier Trial requires WooCommerce.</p></div>';
            });
            return;
        }
        new Admin\Dashboard();
        new Admin\Settings();
        new Api\RestController();
        new Integrations\WooCommerce();
        new Integrations\AbandonedCart();
        new Queue\Processor();
    }

    public function maybe_redirect() {
        if ( get_transient( 'won_trial_redirect' ) ) {
            delete_transient( 'won_trial_redirect' );
            if ( ! isset( $_GET['activate-multi'] ) ) {
                wp_safe_redirect( admin_url( 'admin.php?page=won-dashboard' ) );
                exit;
            }
        }
    }

    /**
     * Show trial countdown banner on ALL admin pages.
     */
    public function trial_banner() {
        $remaining = $this->get_remaining_seconds();
        $hours = floor( $remaining / 3600 );
        $minutes = floor( ( $remaining % 3600 ) / 60 );
        $seconds = $remaining % 60;
        $expiry_ts = $this->get_expiry_time() * 1000; // JS uses milliseconds
        ?>
        <div id="won-trial-banner" style="background:linear-gradient(135deg,#0C1120,#141D2F);border:1px solid rgba(37,211,102,0.3);border-radius:12px;padding:14px 20px;margin:10px 20px 0 2px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <span style="font-size:20px;">⏱️</span>
                <div>
                    <span style="color:#fff;font-weight:700;font-size:13px;">TRIAL MODE</span>
                    <span style="color:#94A3B8;font-size:13px;margin-left:8px;">Expires in:</span>
                    <span id="won-trial-countdown" style="color:#25D366;font-weight:800;font-size:15px;margin-left:6px;font-family:monospace;"><?php printf( '%02d:%02d:%02d', $hours, $minutes, $seconds ); ?></span>
                </div>
            </div>
            <a href="<?php echo esc_url( WON_UPGRADE_URL ); ?>" target="_blank" style="display:inline-block;padding:8px 20px;background:linear-gradient(135deg,#25D366,#128C7E);color:#fff;text-decoration:none;border-radius:8px;font-weight:700;font-size:13px;box-shadow:0 4px 15px rgba(37,211,102,0.3);white-space:nowrap;">🔥 Upgrade to Pro — $19</a>
        </div>
        <script>
        (function(){
            const expiry = <?php echo $expiry_ts; ?>;
            const el = document.getElementById('won-trial-countdown');
            if(!el) return;
            function update(){
                const now = Date.now();
                let diff = Math.max(0, Math.floor((expiry - now)/1000));
                const h = String(Math.floor(diff/3600)).padStart(2,'0');
                const m = String(Math.floor((diff%3600)/60)).padStart(2,'0');
                const s = String(diff%60).padStart(2,'0');
                el.textContent = h+':'+m+':'+s;
                if(diff<=0){el.textContent='EXPIRED';el.style.color='#EF4444';clearInterval(timer);location.reload();}
            }
            const timer = setInterval(update, 1000);
            update();
        })();
        </script>
        <?php
    }

    public function enqueue_trial_assets( $hook ) {
        if ( strpos( $hook, 'won-' ) === false && $hook !== 'toplevel_page_won-dashboard' ) return;
        wp_enqueue_style( 'won-admin', WON_TRIAL_URL . 'assets/css/admin.css', array(), WON_TRIAL_VERSION );
        wp_enqueue_script( 'won-admin', WON_TRIAL_URL . 'assets/js/admin.js', array( 'jquery' ), WON_TRIAL_VERSION, true );
        if ( $hook === 'toplevel_page_won-dashboard' ) {
            wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', array(), '4.4.0', true );
        }
        wp_localize_script( 'won-admin', 'wonAdmin', array(
            'restUrl'  => rest_url( 'won/v1/' ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'darkMode' => get_option( 'won_dark_mode', 'auto' ),
            'isTrial'  => true,
            'upgradeUrl' => WON_UPGRADE_URL,
        ));
    }

    private function set_defaults() {
        $defaults = array(
            'won_hf_space_url' => '', 'won_api_secret' => '', 'won_phone_number' => '',
            'won_country_code' => '+91', 'won_enable_order_alerts' => 'yes',
            'won_enable_stock_alerts' => 'yes', 'won_enable_refund_alerts' => 'yes',
            'won_enable_abandoned_cart' => 'yes', 'won_abandoned_cart_delay' => 60,
            'won_stock_threshold' => 5, 'won_queue_batch_size' => 10,
            'won_retry_attempts' => 3, 'won_log_retention_days' => 30, 'won_dark_mode' => 'auto',
            'won_template_order' => "🛒 *New Order #{order_id}*\n\n👤 {customer_name}\n📱 {customer_phone}\n📧 {customer_email}\n\n📦 Items:\n{order_items}\n\n💰 Total: {order_total}\n💳 Payment: {payment_method}\n📍 {shipping_address}\n\n🕐 {order_date}",
            'won_template_stock' => "⚠️ *{stock_status}*\n\n📦 {product_name}\n🔢 Stock: {stock_quantity}\n🆔 SKU: {product_sku}\n\n⏰ {alert_time}",
            'won_template_refund' => "🔄 *Refund Request*\n\n🛒 Order #{order_id}\n👤 {customer_name}\n💰 Amount: {refund_amount}\n📝 Reason: {refund_reason}\n\n⏰ {refund_date}",
            'won_template_abandoned_cart' => "🛒 *Abandoned Cart*\n\n👤 {customer_name}\n📧 {customer_email}\n\n📦 Items:\n{cart_items}\n\n💰 Value: {cart_total}\n⏰ Abandoned: {abandoned_time}",
        );
        foreach ( $defaults as $k => $v ) { if ( false === get_option( $k ) ) update_option( $k, $v ); }
    }

    private function schedule_events() {
        if ( ! wp_next_scheduled( 'won_process_queue' ) ) wp_schedule_event( time(), 'every_minute', 'won_process_queue' );
        if ( ! wp_next_scheduled( 'won_check_abandoned_carts' ) ) wp_schedule_event( time(), 'every_five_minutes', 'won_check_abandoned_carts' );
        if ( ! wp_next_scheduled( 'won_daily_cleanup' ) ) wp_schedule_event( time(), 'daily', 'won_daily_cleanup' );
    }
}

// Auto-delete files after trial expires (cron callback)
add_action( 'won_trial_delete_files', function() {
    $plugin_dir = WON_TRIAL_DIR;
    if ( is_dir( $plugin_dir ) ) {
        // Use WP Filesystem to delete
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
        global $wp_filesystem;
        $wp_filesystem->delete( $plugin_dir, true );
    }
    delete_option( 'won_trial_deletion_attempted' );
});

// Boot
Trial_Manager::instance();
