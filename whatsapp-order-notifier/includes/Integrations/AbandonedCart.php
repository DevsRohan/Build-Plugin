<?php
namespace suspended_Order_Notifier\Integrations;

use suspended_Order_Notifier\Notifications\Manager;

if ( ! defined( 'ABSPATH' ) ) exit;

class AbandonedCart {

    public function __construct() {
        add_action( 'won_check_abandoned_carts', array( $this, 'check' ) );
    }

    /**
     * Runs every 5 minutes via cron. Marks stale carts as abandoned and sends notifications.
     */
    public function check() {
        if ( 'yes' !== get_option( 'won_enable_abandoned_cart', 'yes' ) ) return;

        $cart_model = new \suspended_Order_Notifier\Database\AbandonedCart();
        $manager = new Manager();

        // Mark carts as abandoned after delay
        $delay = (int) get_option( 'won_abandoned_cart_delay', 60 );
        $cart_model->mark_abandoned( $delay );

        // Get unnotified abandoned carts
        $carts = $cart_model->get_abandoned_unnotified();
        if ( empty( $carts ) ) return;

        foreach ( $carts as $cart ) {
            // Need at least phone or email to be useful
            if ( empty( $cart->customer_phone ) && empty( $cart->customer_email ) ) {
                $cart_model->mark_notified( $cart->id );
                continue;
            }

            $items = json_decode( $cart->cart_contents, true );
            if ( empty( $items ) ) {
                $cart_model->mark_notified( $cart->id );
                continue;
            }

            $items_text = array();
            foreach ( $items as $item ) {
                $items_text[] = sprintf( '• %s x%d — %s',
                    $item['name'], $item['qty'],
                    strip_tags( wc_price( $item['total'], array( 'currency' => $cart->currency ) ) )
                );
            }

            $data = array(
                'customer_name'  => $cart->customer_name ?: 'Customer',
                'customer_email' => $cart->customer_email ?: '',
                'customer_phone' => $cart->customer_phone ?: '',
                'cart_items'     => implode( "\n", $items_text ),
                'cart_total'     => strip_tags( wc_price( $cart->cart_total, array( 'currency' => $cart->currency ) ) ),
                'abandoned_time' => $cart->abandoned_at,
            );

            $manager->queue_notification( 'abandoned_cart', $cart->id, $data );
            $cart_model->mark_notified( $cart->id );
        }
    }
}
