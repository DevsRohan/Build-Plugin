<?php
namespace suspended_Order_Notifier\Queue;

if ( ! defined( 'ABSPATH' ) ) exit;

class Processor {

    public function __construct() {
        add_action( 'won_process_queue', array( $this, 'process' ) );
        add_action( 'won_daily_cleanup', array( $this, 'cleanup' ) );
    }

    /**
     * Process pending queue messages (runs every minute via cron).
     */
    public function process() {
        // Prevent concurrent runs
        if ( get_transient( 'won_queue_lock' ) ) return;
        set_transient( 'won_queue_lock', true, 5 * MINUTE_IN_SECONDS );

        try {
            $queue = new \suspended_Order_Notifier\Database\MessageQueue();
            $log   = new \suspended_Order_Notifier\Database\NotificationLog();
            $api   = new \suspended_Order_Notifier\Api\HFWhatsApp();

            $batch_size = (int) get_option( 'won_queue_batch_size', 10 );
            $messages = $queue->get_pending_batch( $batch_size );

            if ( empty( $messages ) ) {
                delete_transient( 'won_queue_lock' );
                return;
            }

            foreach ( $messages as $msg ) {
                $queue->increment_attempts( $msg->id );
                $queue->update_status( $msg->id, 'processing' );

                $result = $api->send_message( $msg->recipient_phone, $msg->message_content );

                if ( $result['success'] ) {
                    $queue->update_status( $msg->id, 'sent' );
                    $log->insert( array(
                        'notification_type'   => $msg->notification_type,
                        'reference_id'        => $msg->reference_id,
                        'recipient_phone'     => $msg->recipient_phone,
                        'message_content'     => $msg->message_content,
                        'status'              => 'sent',
                        'provider_message_id' => $result['messageId'] ?? '',
                        'attempts'            => $msg->attempts + 1,
                        'sent_at'             => current_time( 'mysql' ),
                    ) );
                } else {
                    $attempts = $msg->attempts + 1;
                    if ( $attempts >= $msg->max_attempts ) {
                        $queue->update_status( $msg->id, 'failed' );
                        $log->insert( array(
                            'notification_type' => $msg->notification_type,
                            'reference_id'      => $msg->reference_id,
                            'recipient_phone'   => $msg->recipient_phone,
                            'message_content'   => $msg->message_content,
                            'status'            => 'failed',
                            'error_message'     => $result['error'] ?? 'Unknown error',
                            'attempts'          => $attempts,
                        ) );
                    } else {
                        // Exponential backoff retry
                        $delay = 300 * pow( 2, $attempts - 1 );
                        $queue->reschedule( $msg->id, min( $delay, 3600 ) );
                    }
                }

                usleep( 500000 ); // 0.5s between messages
            }
        } finally {
            delete_transient( 'won_queue_lock' );
        }
    }

    /**
     * Daily cleanup of old records.
     */
    public function cleanup() {
        $days = (int) get_option( 'won_log_retention_days', 30 );
        ( new \suspended_Order_Notifier\Database\NotificationLog() )->cleanup( $days );
        ( new \suspended_Order_Notifier\Database\MessageQueue() )->cleanup( 7 );
        ( new \suspended_Order_Notifier\Database\AbandonedCart() )->cleanup( $days );
    }
}
