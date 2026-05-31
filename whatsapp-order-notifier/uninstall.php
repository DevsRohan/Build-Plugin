<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}won_notification_log" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}won_message_queue" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}won_abandoned_carts" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'won_%'" );
wp_clear_scheduled_hook( 'won_process_queue' );
wp_clear_scheduled_hook( 'won_check_abandoned_carts' );
wp_clear_scheduled_hook( 'won_daily_cleanup' );
