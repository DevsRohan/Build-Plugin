<?php
namespace suspended_Order_Notifier\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

class Dashboard {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
    }

    public function menu() {
        $icon = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M3 21l1.65-3.8a9 9 0 1 1 3.4 2.9L3 21"/></svg>');

        add_menu_page( 'WhatsApp Notifier', 'WhatsApp Notifier', 'manage_woocommerce', 'won-dashboard', array( $this, 'render_dashboard' ), $icon, 58 );
        add_submenu_page( 'won-dashboard', 'Dashboard', 'Dashboard', 'manage_woocommerce', 'won-dashboard' );
        add_submenu_page( 'won-dashboard', 'Logs', 'Notification Log', 'manage_woocommerce', 'won-logs', array( $this, 'render_logs' ) );
        add_submenu_page( 'won-dashboard', 'Settings', 'Settings', 'manage_woocommerce', 'won-settings', array( $this, 'render_settings' ) );
    }

    public function assets( $hook ) {
        if ( strpos( $hook, 'won-' ) === false && $hook !== 'toplevel_page_won-dashboard' ) return;

        wp_enqueue_style( 'won-admin', WON_PLUGIN_URL . 'assets/css/admin.css', array(), WON_VERSION );
        wp_enqueue_script( 'won-admin', WON_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), WON_VERSION, true );

        if ( $hook === 'toplevel_page_won-dashboard' ) {
            wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', array(), '4.4.0', true );
        }

        wp_localize_script( 'won-admin', 'wonAdmin', array(
            'restUrl' => rest_url( 'won/v1/' ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
            'darkMode' => get_option( 'won_dark_mode', 'auto' ),
        ) );
    }

    public function render_dashboard() { include WON_PLUGIN_DIR . 'templates/admin/dashboard.php'; }
    public function render_logs() { include WON_PLUGIN_DIR . 'templates/admin/logs.php'; }
    public function render_settings() { include WON_PLUGIN_DIR . 'templates/admin/settings.php'; }
}
