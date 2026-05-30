<?php
/**
 * Notification Log Model.
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
 * Notification Log database model.
 *
 * @since 1.0.0
 */
class NotificationLog {

    /**
     * Table name without prefix.
     *
     * @var string
     */
    private $table = 'won_notification_log';

    /**
     * Get full table name.
     *
     * @return string
     */
    private function get_table() {
        global $wpdb;
        return $wpdb->prefix . $this->table;
    }

    /**
     * Insert a new log entry.
     *
     * @param array $data Log data.
     * @return int|false Insert ID or false on failure.
     */
    public function insert( $data ) {
        global $wpdb;

        $defaults = array(
            'notification_type'   => 'order',
            'reference_id'        => null,
            'recipient_phone'     => '',
            'message_content'     => '',
            'status'              => 'pending',
            'provider'            => get_option( 'won_api_provider', 'whatsapp_business' ),
            'provider_message_id' => null,
            'error_message'       => null,
            'attempts'            => 0,
            'sent_at'             => null,
            'created_at'          => current_time( 'mysql' ),
        );

        $data = wp_parse_args( $data, $defaults );

        $result = $wpdb->insert(
            $this->get_table(),
            $data,
            array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update a log entry.
     *
     * @param int   $id   Log entry ID.
     * @param array $data Data to update.
     * @return bool
     */
    public function update( $id, $data ) {
        global $wpdb;

        $data['updated_at'] = current_time( 'mysql' );

        $result = $wpdb->update(
            $this->get_table(),
            $data,
            array( 'id' => absint( $id ) ),
            null,
            array( '%d' )
        );

        return false !== $result;
    }

    /**
     * Get a single log entry.
     *
     * @param int $id Log entry ID.
     * @return object|null
     */
    public function get( $id ) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->get_table()} WHERE id = %d",
                absint( $id )
            )
        );
    }

    /**
     * Get logs with pagination and filtering.
     *
     * @param array $args Query arguments.
     * @return array Array with 'items' and 'total' keys.
     */
    public function get_logs( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'per_page'          => 20,
            'page'              => 1,
            'notification_type' => '',
            'status'            => '',
            'search'            => '',
            'date_from'         => '',
            'date_to'           => '',
            'orderby'           => 'created_at',
            'order'             => 'DESC',
        );

        $args = wp_parse_args( $args, $defaults );

        $where = array( '1=1' );
        $values = array();

        if ( ! empty( $args['notification_type'] ) ) {
            $where[] = 'notification_type = %s';
            $values[] = sanitize_text_field( $args['notification_type'] );
        }

        if ( ! empty( $args['status'] ) ) {
            $where[] = 'status = %s';
            $values[] = sanitize_text_field( $args['status'] );
        }

        if ( ! empty( $args['search'] ) ) {
            $where[] = '(recipient_phone LIKE %s OR message_content LIKE %s)';
            $search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $values[] = $search;
            $values[] = $search;
        }

        if ( ! empty( $args['date_from'] ) ) {
            $where[] = 'created_at >= %s';
            $values[] = sanitize_text_field( $args['date_from'] ) . ' 00:00:00';
        }

        if ( ! empty( $args['date_to'] ) ) {
            $where[] = 'created_at <= %s';
            $values[] = sanitize_text_field( $args['date_to'] ) . ' 23:59:59';
        }

        $where_clause = implode( ' AND ', $where );

        // Allowed orderby columns.
        $allowed_orderby = array( 'id', 'notification_type', 'status', 'created_at', 'sent_at' );
        $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
        $order = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

        $offset = ( absint( $args['page'] ) - 1 ) * absint( $args['per_page'] );

        // Get total count.
        $count_query = "SELECT COUNT(*) FROM {$this->get_table()} WHERE {$where_clause}";
        if ( ! empty( $values ) ) {
            $count_query = $wpdb->prepare( $count_query, $values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }
        $total = (int) $wpdb->get_var( $count_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        // Get items.
        $query = "SELECT * FROM {$this->get_table()} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $values[] = absint( $args['per_page'] );
        $values[] = $offset;

        $items = $wpdb->get_results(
            $wpdb->prepare( $query, $values ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );

        return array(
            'items' => $items,
            'total' => $total,
        );
    }

    /**
     * Get notification statistics.
     *
     * @param string $period Period for stats (today, week, month, all).
     * @return array
     */
    public function get_stats( $period = 'today' ) {
        global $wpdb;

        $where = '1=1';
        switch ( $period ) {
            case 'today':
                $where = "DATE(created_at) = CURDATE()";
                break;
            case 'week':
                $where = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $where = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
        }

        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN notification_type = 'order' THEN 1 ELSE 0 END) as orders,
                SUM(CASE WHEN notification_type = 'stock' THEN 1 ELSE 0 END) as stock_alerts,
                SUM(CASE WHEN notification_type = 'refund' THEN 1 ELSE 0 END) as refunds,
                SUM(CASE WHEN notification_type = 'abandoned_cart' THEN 1 ELSE 0 END) as abandoned_carts
            FROM {$this->get_table()} WHERE {$where}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );

        return array(
            'total'          => (int) ( $stats->total ?? 0 ),
            'sent'           => (int) ( $stats->sent ?? 0 ),
            'failed'         => (int) ( $stats->failed ?? 0 ),
            'pending'        => (int) ( $stats->pending ?? 0 ),
            'orders'         => (int) ( $stats->orders ?? 0 ),
            'stock_alerts'   => (int) ( $stats->stock_alerts ?? 0 ),
            'refunds'        => (int) ( $stats->refunds ?? 0 ),
            'abandoned_carts' => (int) ( $stats->abandoned_carts ?? 0 ),
            'success_rate'   => $stats->total > 0 ? round( ( $stats->sent / $stats->total ) * 100, 1 ) : 0,
        );
    }

    /**
     * Get chart data for notifications over time.
     *
     * @param int $days Number of days to look back.
     * @return array
     */
    public function get_chart_data( $days = 7 ) {
        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(created_at) as date, 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                FROM {$this->get_table()}
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC",
                absint( $days )
            )
        );

        return $results;
    }

    /**
     * Delete old log entries.
     *
     * @param int $days Delete entries older than this many days.
     * @return int Number of rows deleted.
     */
    public function cleanup( $days = 30 ) {
        global $wpdb;

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->get_table()} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                absint( $days )
            )
        );
    }
}
