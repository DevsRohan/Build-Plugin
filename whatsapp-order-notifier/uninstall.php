<?php
/**
 * Uninstall WhatsApp Order Notifier.
 *
 * Removes all plugin data when uninstalled.
 *
 * @package suspended_Order_Notifier
 * @since 1.0.0
 */

// Exit if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Remove all plugin options.
$options = $wpdb->get_results(
    "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'won_%'"
);

if ( $options ) {
    foreach ( $options as $option ) {
        delete_option( $option->option_name );
    }
}

// Drop custom tables.
$tables = array(
    $wpdb->prefix . 'won_notification_log',
    $wpdb->prefix . 'won_message_queue',
    $wpdb->prefix . 'won_abandoned_carts',
    $wpdb->prefix . 'won_templates',
);

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Remove scheduled events.
wp_clear_scheduled_hook( 'won_process_queue' );
wp_clear_scheduled_hook( 'won_check_abandoned_carts' );
wp_clear_scheduled_hook( 'won_daily_cleanup' );

// Clear any transients.
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_won_%' OR option_name LIKE '_transient_timeout_won_%'"
);
