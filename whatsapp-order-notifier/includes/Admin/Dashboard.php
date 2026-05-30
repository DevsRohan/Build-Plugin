<?php
/**
 * Admin Dashboard.
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
 * Handles the admin dashboard interface.
 *
 * @since 1.0.0
 */
class Dashboard {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_filter( 'admin_body_class', array( $this, 'add_body_class' ) );
    }

    /**
     * Register admin menu pages.
     *
     * @return void
     */
    public function register_menu() {
        // Main menu.
        add_menu_page(
            __( 'WhatsApp Notifier', 'suspended-order-notifier' ),
            __( 'WhatsApp Notifier', 'suspended-order-notifier' ),
            'manage_woocommerce',
            'won-dashboard',
            array( $this, 'render_dashboard' ),
            $this->get_menu_icon(),
            58
        );

        // Dashboard submenu.
        add_submenu_page(
            'won-dashboard',
            __( 'Dashboard', 'suspended-order-notifier' ),
            __( 'Dashboard', 'suspended-order-notifier' ),
            'manage_woocommerce',
            'won-dashboard',
            array( $this, 'render_dashboard' )
        );

        // Notification Log submenu.
        add_submenu_page(
            'won-dashboard',
            __( 'Notification Log', 'suspended-order-notifier' ),
            __( 'Notification Log', 'suspended-order-notifier' ),
            'manage_woocommerce',
            'won-logs',
            array( $this, 'render_logs' )
        );

        // Templates submenu.
        add_submenu_page(
            'won-dashboard',
            __( 'Templates', 'suspended-order-notifier' ),
            __( 'Templates', 'suspended-order-notifier' ),
            'manage_woocommerce',
            'won-templates',
            array( $this, 'render_templates' )
        );

        // Settings submenu.
        add_submenu_page(
            'won-dashboard',
            __( 'Settings', 'suspended-order-notifier' ),
            __( 'Settings', 'suspended-order-notifier' ),
            'manage_woocommerce',
            'won-settings',
            array( $this, 'render_settings' )
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_assets( $hook ) {
        // Only load on our pages.
        $our_pages = array(
            'toplevel_page_won-dashboard',
            'whatsapp-notifier_page_won-logs',
            'whatsapp-notifier_page_won-templates',
            'whatsapp-notifier_page_won-settings',
        );

        if ( ! in_array( $hook, $our_pages, true ) ) {
            return;
        }

        // Enqueue styles.
        wp_enqueue_style(
            'won-admin',
            WON_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WON_VERSION
        );

        // Enqueue scripts.
        wp_enqueue_script(
            'won-admin',
            WON_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery', 'wp-api-fetch' ),
            WON_VERSION,
            true
        );

        // Chart.js for dashboard.
        if ( 'toplevel_page_won-dashboard' === $hook ) {
            wp_enqueue_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
                array(),
                '4.4.0',
                true
            );
        }

        // Localize script.
        wp_localize_script(
            'won-admin',
            'wonAdmin',
            array(
                'apiUrl'     => rest_url( 'won/v1/' ),
                'nonce'      => wp_create_nonce( 'wp_rest' ),
                'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
                'pluginUrl'  => WON_PLUGIN_URL,
                'darkMode'   => get_option( 'won_dark_mode', 'auto' ),
                'strings'    => array(
                    'saving'       => __( 'Saving...', 'suspended-order-notifier' ),
                    'saved'        => __( 'Settings saved!', 'suspended-order-notifier' ),
                    'error'        => __( 'An error occurred. Please try again.', 'suspended-order-notifier' ),
                    'testSent'     => __( 'Test message sent successfully!', 'suspended-order-notifier' ),
                    'testFailed'   => __( 'Test message failed.', 'suspended-order-notifier' ),
                    'connected'    => __( 'Connection successful!', 'suspended-order-notifier' ),
                    'disconnected' => __( 'Connection failed.', 'suspended-order-notifier' ),
                    'confirm'      => __( 'Are you sure?', 'suspended-order-notifier' ),
                    'resending'    => __( 'Resending...', 'suspended-order-notifier' ),
                    'resent'       => __( 'Notification queued for resending.', 'suspended-order-notifier' ),
                ),
            )
        );
    }

    /**
     * Add body class to our admin pages.
     *
     * @param string $classes Body classes.
     * @return string
     */
    public function add_body_class( $classes ) {
        $screen = get_current_screen();

        if ( $screen && strpos( $screen->id, 'won-' ) !== false ) {
            $classes .= ' won-admin-page';

            $dark_mode = get_option( 'won_dark_mode', 'auto' );
            if ( 'dark' === $dark_mode ) {
                $classes .= ' won-dark-mode';
            }
        }

        return $classes;
    }

    /**
     * Render the main dashboard page.
     *
     * @return void
     */
    public function render_dashboard() {
        include WON_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }

    /**
     * Render the notification logs page.
     *
     * @return void
     */
    public function render_logs() {
        include WON_PLUGIN_DIR . 'templates/admin/logs.php';
    }

    /**
     * Render the templates page.
     *
     * @return void
     */
    public function render_templates() {
        include WON_PLUGIN_DIR . 'templates/admin/templates.php';
    }

    /**
     * Render the settings page.
     *
     * @return void
     */
    public function render_settings() {
        include WON_PLUGIN_DIR . 'templates/admin/settings.php';
    }

    /**
     * Get the menu icon SVG.
     *
     * @return string Base64 encoded SVG.
     */
    private function get_menu_icon() {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21l1.65-3.8a9 9 0 1 1 3.4 2.9L3 21"/><path d="M9 10a.5.5 0 0 0 1 0V9a.5.5 0 0 0-1 0v1a5 5 0 0 0 5 5h1a.5.5 0 0 0 0-1h-1"/></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode( $svg );
    }
}
