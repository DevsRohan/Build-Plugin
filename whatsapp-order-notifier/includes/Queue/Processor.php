<?php
/**
 * Queue Processor.
 *
 * @package suspended_Order_Notifier\Queue
 * @since 1.0.0
 */

namespace suspended_Order_Notifier\Queue;

use suspended_Order_Notifier\Api\ProviderFactory;
use suspended_Order_Notifier\Database\MessageQueue;
use suspended_Order_Notifier\Database\NotificationLog;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Processes the notification message queue.
 *
 * Handles batch processing, retries, and failure management.
 *
 * @since 1.0.0
 */
class Processor {

    /**
     * Message queue model.
     *
     * @var MessageQueue
     */
    private $queue;

    /**
     * Notification log model.
     *
     * @var NotificationLog
     */
    private $log;

    /**
     * Whether the processor is currently running.
     *
     * @var bool
     */
    private $is_processing = false;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->queue = new MessageQueue();
        $this->log   = new NotificationLog();

        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     *
     * @return void
     */
    private function init_hooks() {
        add_action( 'won_process_queue', array( $this, 'process' ) );
        add_action( 'won_daily_cleanup', array( $this, 'cleanup' ) );
    }

    /**
     * Process the message queue.
     *
     * Called by cron every minute.
     *
     * @return void
     */
    public function process() {
        // Prevent concurrent processing.
        if ( $this->is_locked() ) {
            return;
        }

        $this->lock();
        $this->is_processing = true;

        try {
            $batch_size = (int) get_option( 'won_queue_batch_size', 10 );
            $messages   = $this->queue->get_pending_batch( $batch_size );

            if ( empty( $messages ) ) {
                $this->unlock();
                return;
            }

            foreach ( $messages as $message ) {
                $this->process_message( $message );
            }
        } catch ( \Exception $e ) {
            // Log the error.
            error_log( 'WON Queue Processor Error: ' . $e->getMessage() );
        } finally {
            $this->is_processing = false;
            $this->unlock();
        }
    }

    /**
     * Process a single queue message.
     *
     * @param object $message Queue message object.
     * @return void
     */
    private function process_message( $message ) {
        // Increment attempts.
        $this->queue->increment_attempts( $message->id );

        // Update status to processing.
        $this->queue->update_status( $message->id, 'processing' );

        // Send the message.
        $result = ProviderFactory::send(
            $message->recipient_phone,
            $message->message_content
        );

        if ( $result['success'] ) {
            $this->handle_success( $message, $result );
        } else {
            $this->handle_failure( $message, $result );
        }

        // Small delay between messages to avoid rate limiting.
        usleep( 500000 ); // 0.5 second.
    }

    /**
     * Handle successful message delivery.
     *
     * @param object $message Queue message.
     * @param array  $result  Send result.
     * @return void
     */
    private function handle_success( $message, $result ) {
        // Update queue status.
        $this->queue->update_status( $message->id, 'sent' );

        // Log the successful delivery.
        $this->log->insert(
            array(
                'notification_type'   => $message->notification_type,
                'reference_id'        => $message->reference_id,
                'recipient_phone'     => $message->recipient_phone,
                'message_content'     => $message->message_content,
                'status'              => 'sent',
                'provider_message_id' => $result['message_id'],
                'attempts'            => $message->attempts + 1,
                'sent_at'             => current_time( 'mysql' ),
            )
        );

        /**
         * Action fired after successful message delivery.
         *
         * @param object $message Queue message.
         * @param array  $result  Send result.
         */
        do_action( 'won_message_sent', $message, $result );
    }

    /**
     * Handle failed message delivery.
     *
     * @param object $message Queue message.
     * @param array  $result  Send result.
     * @return void
     */
    private function handle_failure( $message, $result ) {
        $current_attempts = $message->attempts + 1;
        $max_attempts     = $message->max_attempts;

        if ( $current_attempts >= $max_attempts ) {
            // Max attempts reached - mark as permanently failed.
            $this->queue->update_status( $message->id, 'failed' );

            // Log the failure.
            $this->log->insert(
                array(
                    'notification_type' => $message->notification_type,
                    'reference_id'      => $message->reference_id,
                    'recipient_phone'   => $message->recipient_phone,
                    'message_content'   => $message->message_content,
                    'status'            => 'failed',
                    'error_message'     => $result['error'] ?? __( 'Unknown error', 'suspended-order-notifier' ),
                    'attempts'          => $current_attempts,
                )
            );

            /**
             * Action fired when a message permanently fails.
             *
             * @param object $message Queue message.
             * @param array  $result  Send result.
             */
            do_action( 'won_message_failed', $message, $result );
        } else {
            // Retry with exponential backoff.
            $base_delay = (int) get_option( 'won_retry_delay', 300 );
            $delay      = $base_delay * pow( 2, $current_attempts - 1 );

            // Cap at 1 hour.
            $delay = min( $delay, 3600 );

            $this->queue->reschedule( $message->id, $delay );

            /**
             * Action fired when a message is retried.
             *
             * @param object $message  Queue message.
             * @param int    $delay    Retry delay in seconds.
             * @param int    $attempts Current attempt count.
             */
            do_action( 'won_message_retry', $message, $delay, $current_attempts );
        }
    }

    /**
     * Clean up old records.
     *
     * Called by daily cron.
     *
     * @return void
     */
    public function cleanup() {
        $retention_days = (int) get_option( 'won_log_retention_days', 30 );

        // Clean notification logs.
        $this->log->cleanup( $retention_days );

        // Clean processed queue items.
        $this->queue->cleanup( 7 );

        // Clean abandoned carts.
        $cart_model = new \suspended_Order_Notifier\Database\AbandonedCart();
        $cart_model->cleanup( $retention_days );

        /**
         * Action fired after daily cleanup.
         *
         * @param int $retention_days Retention period.
         */
        do_action( 'won_after_cleanup', $retention_days );
    }

    /**
     * Check if the processor is locked (already running).
     *
     * @return bool
     */
    private function is_locked() {
        return (bool) get_transient( 'won_queue_lock' );
    }

    /**
     * Lock the processor.
     *
     * @return void
     */
    private function lock() {
        set_transient( 'won_queue_lock', true, 5 * MINUTE_IN_SECONDS );
    }

    /**
     * Unlock the processor.
     *
     * @return void
     */
    private function unlock() {
        delete_transient( 'won_queue_lock' );
    }

    /**
     * Manually trigger queue processing (for testing or immediate processing).
     *
     * @return array Results summary.
     */
    public function process_now() {
        $batch_size = (int) get_option( 'won_queue_batch_size', 10 );
        $messages   = $this->queue->get_pending_batch( $batch_size );

        $results = array(
            'processed' => 0,
            'sent'      => 0,
            'failed'    => 0,
        );

        if ( empty( $messages ) ) {
            return $results;
        }

        foreach ( $messages as $message ) {
            $this->queue->increment_attempts( $message->id );

            $result = ProviderFactory::send(
                $message->recipient_phone,
                $message->message_content
            );

            $results['processed']++;

            if ( $result['success'] ) {
                $this->handle_success( $message, $result );
                $results['sent']++;
            } else {
                $this->handle_failure( $message, $result );
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Get current queue status.
     *
     * @return array
     */
    public function get_status() {
        return array(
            'is_processing' => $this->is_locked(),
            'stats'         => $this->queue->get_stats(),
        );
    }
}
