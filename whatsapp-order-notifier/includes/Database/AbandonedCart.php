<?php
/**
 * Abandoned Cart Model.
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
 * Abandoned Cart database model.
 *
 * @since 1.0.0
 */
class AbandonedCart {

    /**
     * Table name without prefix.
     *
     * @var string
     */
    private $table = 'won_abandoned_carts';

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
     * Track or update a cart.
     *
     * @param array $data Cart data.
     * @return int|false Insert/update ID or false on failure.
     */
    public function upsert( $data ) {
        global $wpdb;

        $existing = $this->get_by_session( $data['session_id'] );

        if ( $existing ) {
            $wpdb->update(
                $this->get_table(),
                array(
                    'cart_contents'  => $data['cart_contents'] ?? $existing->cart_contents,
                    'cart_total'     => $data['cart_total'] ?? $existing->cart_total,
                    'customer_email' => $data['customer_email'] ?? $existing->customer_email,
                    'customer_name'  => $data['customer_name'] ?? $existing->customer_name,
                    'customer_phone' => $data['customer_phone'] ?? $existing->customer_phone,
                    'status'         => 'active',
                    'updated_at'     => current_time( 'mysql' ),
                ),
                array( 'id' => $existing->id )
            );
            return $existing->id;
        }

        $defaults = array(
            'session_id'        => '',
            'user_id'           => null,
            'customer_email'    => null,
            'customer_name'     => null,
            'customer_phone'    => null,
            'cart_contents'     => '',
            'cart_total'        => 0.00,
            'currency'          => get_woocommerce_currency(),
            'status'            => 'active',
            'notification_sent' => 0,
            'recovered'         => 0,
            'created_at'        => current_time( 'mysql' ),
            'updated_at'        => current_time( 'mysql' ),
        );

        $data = wp_parse_args( $data, $defaults );

        $result = $wpdb->insert( $this->get_table(), $data );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get cart by session ID.
     *
     * @param string $session_id Session identifier.
     * @return object|null
     */
    public function get_by_session( $session_id ) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->get_table()} WHERE session_id = %s",
                sanitize_text_field( $session_id )
            )
        );
    }

    /**
     * Get abandoned carts that need notifications.
     *
     * @param int $delay_minutes Minutes after which a cart is considered abandoned.
     * @return array
     */
    public function get_abandoned( $delay_minutes = 60 ) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->get_table()}
                WHERE status = 'abandoned'
                AND notification_sent = 0
                AND abandoned_at <= DATE_SUB(NOW(), INTERVAL %d MINUTE)
                ORDER BY abandoned_at ASC
                LIMIT 50",
                absint( $delay_minutes )
            )
        );
    }

    /**
     * Mark carts as abandoned if inactive for specified time.
     *
     * @param int $minutes Minutes of inactivity.
     * @return int Number of carts marked.
     */
    public function mark_abandoned( $minutes = 30 ) {
        global $wpdb;

        return $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->get_table()}
                SET status = 'abandoned', abandoned_at = NOW()
                WHERE status = 'active'
                AND updated_at <= DATE_SUB(NOW(), INTERVAL %d MINUTE)",
                absint( $minutes )
            )
        );
    }

    /**
     * Mark a cart as recovered.
     *
     * @param string $session_id Session ID.
     * @return bool
     */
    public function mark_recovered( $session_id ) {
        global $wpdb;

        return (bool) $wpdb->update(
            $this->get_table(),
            array(
                'status'       => 'recovered',
                'recovered'    => 1,
                'recovered_at' => current_time( 'mysql' ),
            ),
            array( 'session_id' => sanitize_text_field( $session_id ) )
        );
    }

    /**
     * Mark notification as sent for a cart.
     *
     * @param int $id Cart ID.
     * @return bool
     */
    public function mark_notified( $id ) {
        global $wpdb;

        return (bool) $wpdb->update(
            $this->get_table(),
            array( 'notification_sent' => 1 ),
            array( 'id' => absint( $id ) )
        );
    }

    /**
     * Remove a cart (when order is placed).
     *
     * @param string $session_id Session ID.
     * @return bool
     */
    public function remove_by_session( $session_id ) {
        global $wpdb;

        return (bool) $wpdb->delete(
            $this->get_table(),
            array( 'session_id' => sanitize_text_field( $session_id ) )
        );
    }

    /**
     * Get abandoned cart statistics.
     *
     * @return array
     */
    public function get_stats() {
        global $wpdb;

        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'abandoned' THEN 1 ELSE 0 END) as abandoned,
                SUM(CASE WHEN status = 'recovered' THEN 1 ELSE 0 END) as recovered,
                SUM(CASE WHEN status = 'abandoned' THEN cart_total ELSE 0 END) as lost_revenue,
                SUM(CASE WHEN status = 'recovered' THEN cart_total ELSE 0 END) as recovered_revenue
            FROM {$this->get_table()}"
        );

        return array(
            'total'             => (int) ( $stats->total ?? 0 ),
            'active'            => (int) ( $stats->active ?? 0 ),
            'abandoned'         => (int) ( $stats->abandoned ?? 0 ),
            'recovered'         => (int) ( $stats->recovered ?? 0 ),
            'lost_revenue'      => (float) ( $stats->lost_revenue ?? 0 ),
            'recovered_revenue' => (float) ( $stats->recovered_revenue ?? 0 ),
            'recovery_rate'     => $stats->abandoned > 0
                ? round( ( $stats->recovered / ( $stats->abandoned + $stats->recovered ) ) * 100, 1 )
                : 0,
        );
    }

    /**
     * Cleanup old cart records.
     *
     * @param int $days Days to retain.
     * @return int Number of rows deleted.
     */
    public function cleanup( $days = 30 ) {
        global $wpdb;

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->get_table()}
                WHERE status IN ('recovered', 'abandoned')
                AND notification_sent = 1
                AND updated_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                absint( $days )
            )
        );
    }
}
