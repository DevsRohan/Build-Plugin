<?php
namespace WON_Trial\Database;

if ( ! defined( 'ABSPATH' ) ) exit;

class MessageQueue {

    private function table() {
        global $wpdb;
        return $wpdb->prefix . 'won_message_queue';
    }

    public function enqueue( $data ) {
        global $wpdb;
        $defaults = array(
            'notification_type' => 'order', 'reference_id' => null,
            'recipient_phone' => '', 'message_content' => '',
            'priority' => 5, 'status' => 'queued', 'attempts' => 0,
            'max_attempts' => (int) get_option( 'won_retry_attempts', 3 ),
            'scheduled_at' => current_time( 'mysql' ),
            'created_at' => current_time( 'mysql' ),
        );
        $data = wp_parse_args( $data, $defaults );

        // Auto priority
        $priority_map = array( 'order' => 1, 'refund' => 2, 'stock' => 3, 'abandoned_cart' => 5 );
        if ( 5 === (int) $data['priority'] && isset( $priority_map[ $data['notification_type'] ] ) ) {
            $data['priority'] = $priority_map[ $data['notification_type'] ];
        }

        $wpdb->insert( $this->table(), $data );
        return $wpdb->insert_id;
    }

    public function get_pending_batch( $batch_size = 10 ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE status = 'queued' AND scheduled_at <= %s AND attempts < max_attempts
             ORDER BY priority ASC, created_at ASC LIMIT %d",
            current_time( 'mysql' ), $batch_size
        ) );
    }

    public function increment_attempts( $id ) {
        global $wpdb;
        $wpdb->query( $wpdb->prepare( "UPDATE {$this->table()} SET attempts = attempts + 1 WHERE id = %d", $id ) );
    }

    public function update_status( $id, $status ) {
        global $wpdb;
        $data = array( 'status' => $status );
        if ( in_array( $status, array( 'sent', 'failed' ), true ) ) {
            $data['processed_at'] = current_time( 'mysql' );
        }
        $wpdb->update( $this->table(), $data, array( 'id' => absint( $id ) ) );
    }

    public function reschedule( $id, $delay = 300 ) {
        global $wpdb;
        $wpdb->update( $this->table(), array(
            'status' => 'queued',
            'scheduled_at' => gmdate( 'Y-m-d H:i:s', time() + $delay ),
        ), array( 'id' => absint( $id ) ) );
    }

    public function get_stats() {
        global $wpdb;
        $row = $wpdb->get_row( "SELECT COUNT(*) as total,
            SUM(status='queued') as queued, SUM(status='sent') as sent, SUM(status='failed') as failed
            FROM {$this->table()}" );
        return array(
            'total' => (int)($row->total??0), 'queued' => (int)($row->queued??0),
            'sent' => (int)($row->sent??0), 'failed' => (int)($row->failed??0),
        );
    }

    public function cleanup( $days = 7 ) {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$this->table()} WHERE status IN ('sent','failed') AND processed_at < DATE_SUB(NOW(), INTERVAL %d DAY)", $days
        ) );
    }
}
