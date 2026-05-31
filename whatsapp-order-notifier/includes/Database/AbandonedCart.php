<?php
namespace suspended_Order_Notifier\Database;

if ( ! defined( 'ABSPATH' ) ) exit;

class AbandonedCart {

    private function table() {
        global $wpdb;
        return $wpdb->prefix . 'won_abandoned_carts';
    }

    public function upsert( $data ) {
        global $wpdb;
        $existing = $this->get_by_session( $data['session_id'] ?? '' );
        if ( $existing ) {
            $wpdb->update( $this->table(), array(
                'cart_contents'  => $data['cart_contents'] ?? $existing->cart_contents,
                'cart_total'     => $data['cart_total'] ?? $existing->cart_total,
                'customer_email' => $data['customer_email'] ?? $existing->customer_email,
                'customer_name'  => $data['customer_name'] ?? $existing->customer_name,
                'customer_phone' => $data['customer_phone'] ?? $existing->customer_phone,
                'status'         => 'active',
            ), array( 'id' => $existing->id ) );
            return $existing->id;
        }

        $defaults = array(
            'session_id' => '', 'user_id' => null, 'customer_email' => '', 'customer_name' => '',
            'customer_phone' => '', 'cart_contents' => '', 'cart_total' => 0,
            'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'INR',
            'status' => 'active', 'notification_sent' => 0, 'created_at' => current_time( 'mysql' ),
        );
        $data = wp_parse_args( $data, $defaults );
        $wpdb->insert( $this->table(), $data );
        return $wpdb->insert_id;
    }

    public function get_by_session( $session_id ) {
        if ( empty( $session_id ) ) return null;
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE session_id = %s", $session_id ) );
    }

    public function mark_abandoned( $minutes = 30 ) {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$this->table()} SET status = 'abandoned', abandoned_at = NOW()
             WHERE status = 'active' AND updated_at <= DATE_SUB(NOW(), INTERVAL %d MINUTE)", $minutes
        ) );
    }

    public function get_abandoned_unnotified() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$this->table()} WHERE status = 'abandoned' AND notification_sent = 0 ORDER BY abandoned_at ASC LIMIT 50"
        );
    }

    public function mark_notified( $id ) {
        global $wpdb;
        $wpdb->update( $this->table(), array( 'notification_sent' => 1 ), array( 'id' => absint( $id ) ) );
    }

    public function mark_recovered( $session_id ) {
        global $wpdb;
        $wpdb->update( $this->table(), array( 'status' => 'recovered' ), array( 'session_id' => $session_id ) );
    }

    public function get_stats() {
        global $wpdb;
        $row = $wpdb->get_row( "SELECT COUNT(*) as total,
            SUM(status='active') as active, SUM(status='abandoned') as abandoned, SUM(status='recovered') as recovered,
            SUM(CASE WHEN status='abandoned' THEN cart_total ELSE 0 END) as lost_revenue,
            SUM(CASE WHEN status='recovered' THEN cart_total ELSE 0 END) as recovered_revenue
            FROM {$this->table()}" );
        $abandoned = (int)($row->abandoned??0);
        $recovered = (int)($row->recovered??0);
        return array(
            'total' => (int)($row->total??0), 'active' => (int)($row->active??0),
            'abandoned' => $abandoned, 'recovered' => $recovered,
            'lost_revenue' => (float)($row->lost_revenue??0),
            'recovered_revenue' => (float)($row->recovered_revenue??0),
            'recovery_rate' => ($abandoned + $recovered) > 0 ? round( $recovered / ($abandoned + $recovered) * 100, 1 ) : 0,
        );
    }

    public function cleanup( $days = 30 ) {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$this->table()} WHERE status IN ('recovered','abandoned') AND notification_sent = 1 AND updated_at < DATE_SUB(NOW(), INTERVAL %d DAY)", $days
        ) );
    }
}
