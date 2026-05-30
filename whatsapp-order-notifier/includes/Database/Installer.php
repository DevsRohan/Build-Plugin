<?php
/**
 * Database Installer.
 *
 * @package suspended_Order_Notifier\Database
 * @since 1.0.0
 */

namespace suspended_Order_Notifier\Database;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles database table creation and upgrades.
 *
 * @since 1.0.0
 */
class Installer {

    /**
     * Install database tables.
     *
     * @return void
     */
    public function install() {
        $this->create_tables();
        update_option( 'won_db_version', WON_DB_VERSION );
    }

    /**
     * Create plugin database tables.
     *
     * @return void
     */
    private function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = array();

        // Notification log table.
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}won_notification_log (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            notification_type VARCHAR(50) NOT NULL DEFAULT 'order',
            reference_id BIGINT(20) UNSIGNED DEFAULT NULL,
            recipient_phone VARCHAR(20) NOT NULL,
            message_content TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            provider VARCHAR(50) NOT NULL DEFAULT 'whatsapp_business',
            provider_message_id VARCHAR(255) DEFAULT NULL,
            error_message TEXT DEFAULT NULL,
            attempts TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
            sent_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_notification_type (notification_type),
            KEY idx_status (status),
            KEY idx_reference_id (reference_id),
            KEY idx_created_at (created_at),
            KEY idx_recipient_phone (recipient_phone)
        ) {$charset_collate};";

        // Message queue table.
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}won_message_queue (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            notification_type VARCHAR(50) NOT NULL DEFAULT 'order',
            reference_id BIGINT(20) UNSIGNED DEFAULT NULL,
            recipient_phone VARCHAR(20) NOT NULL,
            message_content TEXT NOT NULL,
            priority TINYINT(3) UNSIGNED NOT NULL DEFAULT 5,
            status VARCHAR(20) NOT NULL DEFAULT 'queued',
            attempts TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
            max_attempts TINYINT(3) UNSIGNED NOT NULL DEFAULT 3,
            scheduled_at DATETIME DEFAULT NULL,
            processed_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status_priority (status, priority),
            KEY idx_scheduled_at (scheduled_at),
            KEY idx_notification_type (notification_type)
        ) {$charset_collate};";

        // Abandoned carts table.
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}won_abandoned_carts (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id VARCHAR(255) NOT NULL,
            user_id BIGINT(20) UNSIGNED DEFAULT NULL,
            customer_email VARCHAR(255) DEFAULT NULL,
            customer_name VARCHAR(255) DEFAULT NULL,
            customer_phone VARCHAR(20) DEFAULT NULL,
            cart_contents LONGTEXT NOT NULL,
            cart_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            currency VARCHAR(3) NOT NULL DEFAULT 'INR',
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            notification_sent TINYINT(1) NOT NULL DEFAULT 0,
            recovered TINYINT(1) NOT NULL DEFAULT 0,
            abandoned_at DATETIME DEFAULT NULL,
            recovered_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_session_id (session_id),
            KEY idx_user_id (user_id),
            KEY idx_status (status),
            KEY idx_abandoned_at (abandoned_at),
            KEY idx_notification_sent (notification_sent)
        ) {$charset_collate};";

        // Custom templates table.
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}won_templates (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            template_name VARCHAR(100) NOT NULL,
            template_type VARCHAR(50) NOT NULL DEFAULT 'order',
            template_content TEXT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by BIGINT(20) UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_template_type (template_type),
            KEY idx_is_active (is_active)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach ( $sql as $query ) {
            dbDelta( $query );
        }
    }

    /**
     * Upgrade database if needed.
     *
     * @return void
     */
    public function maybe_upgrade() {
        $current_version = get_option( 'won_db_version', '0' );

        if ( version_compare( $current_version, WON_DB_VERSION, '<' ) ) {
            $this->install();
        }
    }
}
