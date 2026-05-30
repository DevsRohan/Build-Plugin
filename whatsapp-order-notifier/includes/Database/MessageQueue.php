<?php
/**
 * Message Queue Model.
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
 * Message Queue database model.
 *
 * @since 1.0.0
 */
class MessageQueue {

    /**
     * Table name without prefix.
     *
     * @var string
     */
    private $table = 'won_message_queue';

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
     * Add a message to the queue.
     *
     * @param array $data Message data.
     * @return int|false Insert ID or false on failure.
     */
    public function enqueue( $data ) {
        global $wpdb;

        $defaults = array(
            'notification_type' => 'order',
            'reference_id'      => null,
            'recipient_phone'   => '',
            'message_content'   => '',
            'priority'          => 5,
            'status'            => 'queued',
            'attempts'          => 0,
            'max_attempts'      => (int) get_option( 'won_retry_attempts', 3 ),
            'scheduled_at'      => current_time( 'mysql' ),
            'created_at'        => current_time( 'mysql' ),
        );

        $data = wp_parse_args( $data, $defaults );

        // Priority mapping: order=1, refund=2, stock=3, abandoned_cart=5.
        if ( empty( $data['priority'] ) || 5 === $data['priority'] ) {
            $priority_map = array(
                'order'          => 1,
                'refund'         => 2,
                'stock'          => 3,
                'abandoned_cart' => 5,
            );
            $data['priority'] = $priority_map[ $data['notification_type'] ] ?? 5;
        }

        $result = $wpdb->insert(
            $this->get_table(),
            $data,
            array( '%s', '%d', '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%s' )
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get next batch of messages to process.
     *
     * @param int $batch_size Number of messages to fetch.
     * @return array
     */
    public function get_pending_batch( $batch_size = 10 ) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->get_table()}
                WHERE status = 'queued'
                AND scheduled_at <= %s
                AND attempts < max_attempts
                ORDER BY priority ASC, created_at ASC
                LIMIT %d",
                current_time( 'mysql' ),
                absint( $batch_size )
            )
        );
    }

    /**
     * Mark a message as processing.
     *
     * @param int $id Message ID.
     * @return bool
     */
    public function mark_processing( $id ) {
        global $wpdb;

        return (bool) $wpdb->update(
            $this->get_table(),
            array(
                'status'   => 'processing',
                'attempts' => new \stdClass(), // Will be incremented in raw SQL.
            ),
            array( 'id' => absint( $id ) )
        );
    }

    /**
     * Increment attempts and update status.
     *
     * @param int    $id     Message ID.
     * @param string $status New status.
     * @return bool
     */
    public function update_status( $id, $status ) {
        global $wpdb;

        $data = array( 'status' => $status );

        if ( 'sent' === $status || 'failed' === $status ) {
            $data['processed_at'] = current_time( 'mysql' );
        }

        return (bool) $wpdb->update(
            $this->get_table(),
            $data,
            array( 'id' => absint( $id ) )
        );
    }

    /**
     * Increment the attempts counter.
     *
     * @param int $id Message ID.
     * @return bool
     */
    public function increment_attempts( $id ) {
        global $wpdb;

        return (bool) $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->get_table()} SET attempts = attempts + 1 WHERE id = %d",
                absint( $id )
            )
        );
    }

    /**
     * Reschedule a failed message for retry.
     *
     * @param int $id    Message ID.
     * @param int $delay Delay in seconds before retry.
     * @return bool
     */
    public function reschedule( $id, $delay = 300 ) {
        global $wpdb;

        $scheduled_at = gmdate( 'Y-m-d H:i:s', time() + absint( $delay ) );

        return (bool) $wpdb->update(
            $this->get_table(),
            array(
                'status'       => 'queued',
                'scheduled_at' => $scheduled_at,
            ),
            array( 'id' => absint( $id ) )
        );
    }

    /**
     * Get queue statistics.
     *
     * @return array
     */
    public function get_stats() {
        global $wpdb;

        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) as queued,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM {$this->get_table()}"
        );

        return array(
            'total'      => (int) ( $stats->total ?? 0 ),
            'queued'     => (int) ( $stats->queued ?? 0 ),
            'processing' => (int) ( $stats->processing ?? 0 ),
            'sent'       => (int) ( $stats->sent ?? 0 ),
            'failed'     => (int) ( $stats->failed ?? 0 ),
        );
    }

    /**
     * Clear processed messages older than specified days.
     *
     * @param int $days Days to retain.
     * @return int Number of rows deleted.
     */
    public function cleanup( $days = 7 ) {
        global $wpdb;

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->get_table()} 
                WHERE status IN ('sent', 'failed') 
                AND processed_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                absint( $days )
            )
        );
    }
}
