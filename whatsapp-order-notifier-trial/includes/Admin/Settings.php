<?php
namespace WON_Trial\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

class Settings {
    public function __construct() {
        add_filter( 'plugin_action_links_' . WON_TRIAL_BASENAME, array( $this, 'links' ) );
    }

    public function links( $links ) {
        array_unshift( $links, '<a href="' . esc_url( WON_UPGRADE_URL ) . '" style="color:#25D366;font-weight:bold;" target="_blank">🔥 Upgrade Pro</a>' );
        array_unshift( $links, '<a href="' . admin_url( 'admin.php?page=won-settings' ) . '">Settings</a>' );
        return $links;
    }
}
