<?php
/**
 * Notification Manager.
 *
 * @package suspended_Order_Notifier\Notifications
 * @since 1.0.0
 */

namespace suspended_Order_Notifier\Notifications;

use suspended_Order_Notifier\Database\MessageQueue;
use suspended_Order_Notifier\Database\NotificationLog;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Central notification management system.
 *
 * Handles message template rendering, phone number resolution,
 * and queuing notifications for delivery.
 *
 * @since 1.0.0
 */
class Manager {

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
     * Message builder instance.
     *
     * @var MessageBuilder
     */
    private $builder;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->queue   = new MessageQueue();
        $this->log     = new NotificationLog();
        $this->builder = new MessageBuilder();
    }

    /**
     * Queue a notification for delivery.
     *
     * @param string      $type         Notification type (order, stock, refund, abandoned_cart).
     * @param int         $reference_id Reference ID (order ID, product ID, cart ID).
     * @param array       $data         Template data.
     * @param string|null $custom_message Custom message (overrides template).
     * @return int|false Queue ID or false on failure.
     */
    public function queue_notification( $type, $reference_id, $data, $custom_message = null ) {
        // Get recipient phone number.
        $phone = $this->get_recipient_phone( $type, $data );

        if ( empty( $phone ) ) {
            // Log the failure.
            $this->log->insert(
                array(
                    'notification_type' => $type,
                    'reference_id'      => $reference_id,
                    'recipient_phone'   => 'unknown',
                    'message_content'   => 'Failed: No recipient phone number available.',
                    'status'            => 'failed',
                    'error_message'     => __( 'No recipient phone number configured or available.', 'suspended-order-notifier' ),
                )
            );
            return false;
        }

        // Build the message.
        if ( $custom_message ) {
            $message = $this->builder->render_placeholders( $custom_message, $data );
        } else {
            $message = $this->builder->build_message( $type, $data );
        }

        if ( empty( $message ) ) {
            return false;
        }

        /**
         * Filter the notification message before queuing.
         *
         * @param string $message      The message content.
         * @param string $type         Notification type.
         * @param int    $reference_id Reference ID.
         * @param array  $data         Template data.
         */
        $message = apply_filters( 'won_notification_message', $message, $type, $reference_id, $data );

        /**
         * Filter the recipient phone number.
         *
         * @param string $phone        Recipient phone.
         * @param string $type         Notification type.
         * @param int    $reference_id Reference ID.
         * @param array  $data         Template data.
         */
        $phone = apply_filters( 'won_notification_phone', $phone, $type, $reference_id, $data );

        // Add to queue.
        $queue_id = $this->queue->enqueue(
            array(
                'notification_type' => $type,
                'reference_id'      => $reference_id,
                'recipient_phone'   => $phone,
                'message_content'   => $message,
            )
        );

        if ( $queue_id ) {
            /**
             * Action fired after notification is queued.
             *
             * @param int    $queue_id     Queue item ID.
             * @param string $type         Notification type.
             * @param int    $reference_id Reference ID.
             * @param string $phone        Recipient phone.
             */
            do_action( 'won_notification_queued', $queue_id, $type, $reference_id, $phone );
        }

        return $queue_id;
    }

    /**
     * Send a notification immediately (bypass queue).
     *
     * @param string $type         Notification type.
     * @param int    $reference_id Reference ID.
     * @param array  $data         Template data.
     * @return array Send result.
     */
    public function send_immediate( $type, $reference_id, $data ) {
        $phone   = $this->get_recipient_phone( $type, $data );
        $message = $this->builder->build_message( $type, $data );

        if ( empty( $phone ) || empty( $message ) ) {
            return array(
                'success' => false,
                'error'   => __( 'Missing phone number or message content.', 'suspended-order-notifier' ),
            );
        }

        $result = \suspended_Order_Notifier\Api\ProviderFactory::send( $phone, $message );

        // Log the result.
        $this->log->insert(
            array(
                'notification_type'   => $type,
                'reference_id'        => $reference_id,
                'recipient_phone'     => $phone,
                'message_content'     => $message,
                'status'              => $result['success'] ? 'sent' : 'failed',
                'provider_message_id' => $result['message_id'] ?? null,
                'error_message'       => $result['error'] ?? null,
                'sent_at'             => $result['success'] ? current_time( 'mysql' ) : null,
            )
        );

        return $result;
    }

    /**
     * Get the recipient phone number based on notification type.
     *
     * @param string $type Notification type.
     * @param array  $data Template data.
     * @return string Phone number with country code.
     */
    private function get_recipient_phone( $type, $data ) {
        // The primary recipient is always the store owner/admin.
        $admin_phone  = get_option( 'won_phone_number', '' );
        $country_code = get_option( 'won_country_code', '+91' );

        if ( ! empty( $admin_phone ) ) {
            // Ensure country code is prepended.
            if ( strpos( $admin_phone, '+' ) !== 0 ) {
                $admin_phone = $country_code . $admin_phone;
            }
            return $admin_phone;
        }

        // Fallback: try to get phone from data.
        if ( ! empty( $data['customer_phone'] ) ) {
            $phone = $data['customer_phone'];
            if ( strpos( $phone, '+' ) !== 0 ) {
                $phone = $country_code . $phone;
            }
            return $phone;
        }

        return '';
    }

    /**
     * Get notification statistics for a specific period.
     *
     * @param string $period Period (today, week, month, all).
     * @return array
     */
    public function get_stats( $period = 'today' ) {
        return $this->log->get_stats( $period );
    }
}
