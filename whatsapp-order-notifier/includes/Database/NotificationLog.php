<?php
namespace suspended_Order_Notifier\Database;

if ( ! defined( 'ABSPATH' ) ) exit;

class NotificationLog {

    private function table() {
        global $wpdb;
        return $wpdb->prefix . 'won_notification_log';
    }

    public function insert( $data ) {
        global $wpdb;
        $defaults = array(
            'notification_type' => 'order', 'reference_id' => null,
            'recipient_phone' => '', 'message_content' => '',
            'status' => 'pending', 'provider_message_id' => null,
            'error_message' => null, 'attempts' => 0, 'sent_at' => null,
            'created_at' => current_time( 'mysql' ),
        );
        $data = wp_parse_args( $data, $defaults );
        $wpdb->insert( $this->table(), $data );
        return $wpdb->insert_id;
    }

    public function update( $id, $data ) {
        global $wpdb;
        return $wpdb->update( $this->table(), $data, array( 'id' => absint( $id ) ) );
    }

    public function get( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE id = %d", $id ) );
    }

    public function get_logs( $args = array() ) {
        global $wpdb;
        $args = wp_parse_args( $args, array(
            'per_page' => 20, 'page' => 1, 'notification_type' => '', 'status' => '', 'search' => '',
        ) );

        $where = array( '1=1' );
        $values = array();

        if ( $args['notification_type'] ) {
            $where[] = 'notification_type = %s';
            $values[] = $args['notification_type'];
        }
        if ( $args['status'] ) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }
        if ( $args['search'] ) {
            $where[] = 'recipient_phone LIKE %s';
            $values[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
        }

        $where_sql = implode( ' AND ', $where );
        $offset = ( absint( $args['page'] ) - 1 ) * absint( $args['per_page'] );

        $count_sql = "SELECT COUNT(*) FROM {$this->table()} WHERE {$where_sql}";
        $total = $values ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) ) : (int) $wpdb->get_var( $count_sql );

        $query = "SELECT * FROM {$this->table()} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $values[] = absint( $args['per_page'] );
        $values[] = $offset;
        $items = $wpdb->get_results( $wpdb->prepare( $query, $values ) );

        return array( 'items' => $items, 'total' => $total );
    }

    public function get_stats( $period = 'today' ) {
        global $wpdb;
        $where = '1=1';
        if ( 'today' === $period ) $where = "DATE(created_at) = CURDATE()";
        elseif ( 'week' === $period ) $where = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        elseif ( 'month' === $period ) $where = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";

        $row = $wpdb->get_row( "SELECT COUNT(*) as total,
            SUM(status='sent') as sent, SUM(status='failed') as failed, SUM(status='pending') as pending,
            SUM(notification_type='order') as orders, SUM(notification_type='stock') as stock_alerts,
            SUM(notification_type='refund') as refunds, SUM(notification_type='abandoned_cart') as abandoned_carts
            FROM {$this->table()} WHERE {$where}" );

        $total = (int) ($row->total ?? 0);
        return array(
            'total' => $total, 'sent' => (int)($row->sent??0), 'failed' => (int)($row->failed??0),
            'pending' => (int)($row->pending??0), 'orders' => (int)($row->orders??0),
            'stock_alerts' => (int)($row->stock_alerts??0), 'refunds' => (int)($row->refunds??0),
            'abandoned_carts' => (int)($row->abandoned_carts??0),
            'success_rate' => $total > 0 ? round( (int)($row->sent??0) / $total * 100, 1 ) : 0,
        );
    }

    public function get_chart_data( $days = 7 ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as total, SUM(status='sent') as sent, SUM(status='failed') as failed
             FROM {$this->table()} WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY DATE(created_at) ORDER BY date ASC", $days
        ) );
    }

    public function cleanup( $days = 30 ) {
        global $wpdb;
        return $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->table()} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)", $days ) );
    }
}
