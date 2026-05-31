<?php
namespace WON_Trial\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

class Dashboard {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'menu' ) );
    }

    public function menu() {
        $icon = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M3 21l1.65-3.8a9 9 0 1 1 3.4 2.9L3 21"/></svg>');
        add_menu_page( 'WA Notifier Trial', 'WA Notifier ⏱️', 'manage_woocommerce', 'won-dashboard', array( $this, 'render_dashboard' ), $icon, 58 );
        add_submenu_page( 'won-dashboard', 'Dashboard', 'Dashboard', 'manage_woocommerce', 'won-dashboard' );
        add_submenu_page( 'won-dashboard', 'Logs', 'Notification Log', 'manage_woocommerce', 'won-logs', array( $this, 'render_logs' ) );
        add_submenu_page( 'won-dashboard', 'Settings', 'Settings', 'manage_woocommerce', 'won-settings', array( $this, 'render_settings' ) );
        add_submenu_page( 'won-dashboard', 'Upgrade', '🔥 Upgrade to Pro', 'manage_woocommerce', 'won-upgrade', array( $this, 'render_upgrade' ) );
    }

    public function render_dashboard() { include WON_TRIAL_DIR . 'templates/admin/dashboard.php'; }
    public function render_logs() { include WON_TRIAL_DIR . 'templates/admin/logs.php'; }
    public function render_settings() { include WON_TRIAL_DIR . 'templates/admin/settings.php'; }
    public function render_upgrade() {
        wp_redirect( WON_UPGRADE_URL );
        exit;
    }
}
