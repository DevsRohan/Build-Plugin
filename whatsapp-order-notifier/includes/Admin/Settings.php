<?php
namespace suspended_Order_Notifier\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

class Settings {

    public function __construct() {
        add_filter( 'plugin_action_links_' . WON_PLUGIN_BASENAME, array( $this, 'links' ) );
    }

    public function links( $links ) {
        array_unshift( $links, '<a href="' . admin_url( 'admin.php?page=won-settings' ) . '">Settings</a>' );
        array_unshift( $links, '<a href="' . admin_url( 'admin.php?page=won-dashboard' ) . '">Dashboard</a>' );
        return $links;
    }
}
