<?php
namespace WON_Trial\Database;

if ( ! defined( 'ABSPATH' ) ) exit;

class Installer {
    public function install() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql = array();

        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}won_notification_log (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            notification_type VARCHAR(50) NOT NULL DEFAULT 'order',
            reference_id BIGINT UNSIGNED DEFAULT NULL,
            recipient_phone VARCHAR(20) NOT NULL,
            message_content TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            provider_message_id VARCHAR(255) DEFAULT NULL,
            error_message TEXT DEFAULT NULL,
            attempts TINYINT UNSIGNED DEFAULT 0,
            sent_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_type (notification_type),
            KEY idx_status (status),
            KEY idx_created (created_at)
        ) {$charset};";

        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}won_message_queue (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            notification_type VARCHAR(50) NOT NULL DEFAULT 'order',
            reference_id BIGINT UNSIGNED DEFAULT NULL,
            recipient_phone VARCHAR(20) NOT NULL,
            message_content TEXT NOT NULL,
            priority TINYINT UNSIGNED DEFAULT 5,
            status VARCHAR(20) NOT NULL DEFAULT 'queued',
            attempts TINYINT UNSIGNED DEFAULT 0,
            max_attempts TINYINT UNSIGNED DEFAULT 3,
            scheduled_at DATETIME DEFAULT NULL,
            processed_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status_priority (status, priority),
            KEY idx_scheduled (scheduled_at)
        ) {$charset};";

        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}won_abandoned_carts (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            session_id VARCHAR(255) NOT NULL,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            customer_email VARCHAR(255) DEFAULT NULL,
            customer_name VARCHAR(255) DEFAULT NULL,
            customer_phone VARCHAR(20) DEFAULT NULL,
            cart_contents LONGTEXT NOT NULL,
            cart_total DECIMAL(10,2) DEFAULT 0.00,
            currency VARCHAR(3) DEFAULT 'INR',
            status VARCHAR(20) DEFAULT 'active',
            notification_sent TINYINT(1) DEFAULT 0,
            abandoned_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_session (session_id),
            KEY idx_status (status)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ( $sql as $query ) {
            dbDelta( $query );
        }

        update_option( 'won_db_version', WON_TRIAL_VERSION );
    }
}
