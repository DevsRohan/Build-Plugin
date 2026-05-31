<?php
namespace suspended_Order_Notifier\Notifications;

if ( ! defined( 'ABSPATH' ) ) exit;

class Manager {

    /**
     * Queue a notification for sending.
     */
    public function queue_notification( $type, $reference_id, $data, $custom_message = null ) {
        $phone = $this->get_admin_phone();
        if ( empty( $phone ) ) return false;

        $message = $custom_message ?: $this->build_message( $type, $data );
        if ( empty( $message ) ) return false;

        $message = apply_filters( 'won_notification_message', $message, $type, $reference_id, $data );

        $queue = new \suspended_Order_Notifier\Database\MessageQueue();
        return $queue->enqueue( array(
            'notification_type' => $type,
            'reference_id'      => $reference_id,
            'recipient_phone'   => $phone,
            'message_content'   => $message,
        ) );
    }

    /**
     * Build message from template + data.
     */
    public function build_message( $type, $data ) {
        $template = get_option( 'won_template_' . $type, '' );
        if ( empty( $template ) ) {
            $template = $this->get_default_template( $type );
        }

        // Replace placeholders
        foreach ( $data as $key => $value ) {
            if ( is_string( $value ) || is_numeric( $value ) ) {
                $template = str_replace( '{' . $key . '}', (string) $value, $template );
            }
        }
        $template = str_replace( '{site_name}', get_bloginfo( 'name' ), $template );
        $template = str_replace( '{current_time}', current_time( 'Y-m-d H:i:s' ), $template );

        // Strip HTML (from wc_price etc), remove unreplaced placeholders
        $template = wp_strip_all_tags( $template );
        $template = preg_replace( '/\{[a-z_]+\}/', '', $template );
        $template = preg_replace( '/\n{3,}/', "\n\n", $template );

        return trim( $template );
    }

    /**
     * Get admin phone number with country code.
     */
    private function get_admin_phone() {
        $phone = get_option( 'won_phone_number', '' );
        if ( empty( $phone ) ) return '';

        if ( strpos( $phone, '+' ) !== 0 ) {
            $phone = get_option( 'won_country_code', '+91' ) . $phone;
        }
        return $phone;
    }

    private function get_default_template( $type ) {
        $defaults = array(
            'order' => "🛒 *New Order #{order_id}*\n\n👤 {customer_name}\n📱 {customer_phone}\n📧 {customer_email}\n\n📦 Items:\n{order_items}\n\n💰 Total: {order_total}\n💳 Payment: {payment_method}\n📍 {shipping_address}\n\n🕐 {order_date}",
            'stock' => "⚠️ *{stock_status}*\n\n📦 {product_name}\n🔢 Stock: {stock_quantity}\n🆔 SKU: {product_sku}\n\n⏰ {alert_time}",
            'refund' => "🔄 *Refund Request*\n\n🛒 Order #{order_id}\n👤 {customer_name}\n💰 Amount: {refund_amount}\n📝 Reason: {refund_reason}\n\n⏰ {refund_date}",
            'abandoned_cart' => "🛒 *Abandoned Cart*\n\n👤 {customer_name}\n📧 {customer_email}\n\n📦 Items:\n{cart_items}\n\n💰 Value: {cart_total}\n⏰ Abandoned: {abandoned_time}",
        );
        return $defaults[ $type ] ?? $defaults['order'];
    }
}
