<?php
/**
 * Message Builder.
 *
 * @package suspended_Order_Notifier\Notifications
 * @since 1.0.0
 */

namespace suspended_Order_Notifier\Notifications;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Builds WhatsApp messages from templates and data.
 *
 * Handles placeholder replacement, message formatting,
 * and template management.
 *
 * @since 1.0.0
 */
class MessageBuilder {

    /**
     * Build a message from a template and data.
     *
     * @param string $type Notification type.
     * @param array  $data Template data.
     * @return string Rendered message.
     */
    public function build_message( $type, $data ) {
        $template = $this->get_template( $type );

        if ( empty( $template ) ) {
            $template = $this->get_default_template( $type );
        }

        return $this->render_placeholders( $template, $data );
    }

    /**
     * Render placeholders in a template string.
     *
     * @param string $template Template with {placeholder} markers.
     * @param array  $data     Key-value pairs for replacement.
     * @return string Rendered message.
     */
    public function render_placeholders( $template, $data ) {
        // Standard placeholder replacement.
        foreach ( $data as $key => $value ) {
            if ( is_string( $value ) || is_numeric( $value ) ) {
                $template = str_replace( '{' . $key . '}', (string) $value, $template );
            }
        }

        // Add dynamic placeholders.
        $template = str_replace( '{site_name}', get_bloginfo( 'name' ), $template );
        $template = str_replace( '{site_url}', home_url(), $template );
        $template = str_replace( '{current_time}', current_time( 'Y-m-d H:i:s' ), $template );
        $template = str_replace( '{current_date}', current_time( 'Y-m-d' ), $template );

        // Strip HTML tags that might be present (from WooCommerce price formatting etc).
        $template = wp_strip_all_tags( $template );

        // Clean up any unreplaced placeholders.
        $template = preg_replace( '/\{[a-z_]+\}/', '', $template );

        // Clean up multiple blank lines.
        $template = preg_replace( '/\n{3,}/', "\n\n", $template );

        return trim( $template );
    }

    /**
     * Get the stored template for a notification type.
     *
     * @param string $type Notification type.
     * @return string Template string.
     */
    public function get_template( $type ) {
        $option_key = 'won_message_template_' . $type;
        $template = get_option( $option_key, '' );

        /**
         * Filter the message template.
         *
         * @param string $template Template content.
         * @param string $type     Notification type.
         */
        return apply_filters( 'won_message_template', $template, $type );
    }

    /**
     * Get the default template for a notification type.
     *
     * @param string $type Notification type.
     * @return string Default template.
     */
    public function get_default_template( $type ) {
        $templates = $this->get_default_templates();
        return $templates[ $type ] ?? $templates['order'];
    }

    /**
     * Get all default templates.
     *
     * @return array
     */
    public function get_default_templates() {
        return array(
            'order' => "🛒 *New Order #{order_id}*\n\n" .
                "👤 {customer_name}\n" .
                "📱 {customer_phone}\n" .
                "📧 {customer_email}\n\n" .
                "📦 Items:\n{order_items}\n\n" .
                "💰 Total: {order_total}\n" .
                "💳 Payment: {payment_method}\n" .
                "📍 Shipping: {shipping_address}\n\n" .
                "🕐 {order_date}",

            'order_status' => "📋 *Order Status Update*\n\n" .
                "🛒 Order #{order_id}\n" .
                "👤 {customer_name}\n\n" .
                "📊 Status: {old_status} → *{new_status}*\n" .
                "💰 Total: {order_total}\n\n" .
                "🕐 {current_time}",

            'stock' => "⚠️ *{stock_status} Alert*\n\n" .
                "📦 {product_name}\n" .
                "🔢 Stock: {stock_quantity}\n" .
                "🆔 SKU: {product_sku}\n\n" .
                "⏰ {alert_time}",

            'refund' => "🔄 *Refund Request*\n\n" .
                "🛒 Order #{order_id}\n" .
                "👤 {customer_name}\n" .
                "💰 Refund Amount: {refund_amount}\n" .
                "📝 Reason: {refund_reason}\n\n" .
                "💵 Order Total: {order_total}\n" .
                "⏰ {refund_date}",

            'abandoned_cart' => "🛒 *Abandoned Cart Alert*\n\n" .
                "👤 {customer_name}\n" .
                "📧 {customer_email}\n" .
                "📱 {customer_phone}\n\n" .
                "📦 Items:\n{cart_items}\n\n" .
                "💰 Cart Value: {cart_total}\n" .
                "⏰ Abandoned: {abandoned_time}",
        );
    }

    /**
     * Get available placeholders for a notification type.
     *
     * @param string $type Notification type.
     * @return array
     */
    public function get_available_placeholders( $type ) {
        $common = array(
            '{site_name}'    => __( 'Site name', 'suspended-order-notifier' ),
            '{site_url}'     => __( 'Site URL', 'suspended-order-notifier' ),
            '{current_time}' => __( 'Current date and time', 'suspended-order-notifier' ),
            '{current_date}' => __( 'Current date', 'suspended-order-notifier' ),
        );

        $type_placeholders = array(
            'order' => array(
                '{order_id}'         => __( 'Order ID', 'suspended-order-notifier' ),
                '{order_number}'     => __( 'Order number', 'suspended-order-notifier' ),
                '{customer_name}'    => __( 'Customer full name', 'suspended-order-notifier' ),
                '{customer_email}'   => __( 'Customer email', 'suspended-order-notifier' ),
                '{customer_phone}'   => __( 'Customer phone', 'suspended-order-notifier' ),
                '{order_items}'      => __( 'List of ordered items', 'suspended-order-notifier' ),
                '{order_total}'      => __( 'Order total with currency', 'suspended-order-notifier' ),
                '{payment_method}'   => __( 'Payment method', 'suspended-order-notifier' ),
                '{shipping_method}'  => __( 'Shipping method', 'suspended-order-notifier' ),
                '{shipping_address}' => __( 'Shipping address', 'suspended-order-notifier' ),
                '{billing_address}'  => __( 'Billing address', 'suspended-order-notifier' ),
                '{order_date}'       => __( 'Order date', 'suspended-order-notifier' ),
                '{order_status}'     => __( 'Order status', 'suspended-order-notifier' ),
                '{order_notes}'      => __( 'Customer notes', 'suspended-order-notifier' ),
                '{items_count}'      => __( 'Number of items', 'suspended-order-notifier' ),
            ),
            'order_status' => array(
                '{order_id}'      => __( 'Order ID', 'suspended-order-notifier' ),
                '{customer_name}' => __( 'Customer name', 'suspended-order-notifier' ),
                '{old_status}'    => __( 'Previous status', 'suspended-order-notifier' ),
                '{new_status}'    => __( 'New status', 'suspended-order-notifier' ),
                '{order_total}'   => __( 'Order total', 'suspended-order-notifier' ),
            ),
            'stock' => array(
                '{product_name}'   => __( 'Product name', 'suspended-order-notifier' ),
                '{product_id}'     => __( 'Product ID', 'suspended-order-notifier' ),
                '{product_sku}'    => __( 'Product SKU', 'suspended-order-notifier' ),
                '{stock_quantity}' => __( 'Current stock quantity', 'suspended-order-notifier' ),
                '{stock_status}'   => __( 'Stock status (LOW STOCK/OUT OF STOCK)', 'suspended-order-notifier' ),
                '{alert_type}'     => __( 'Alert type (low/out)', 'suspended-order-notifier' ),
                '{alert_time}'     => __( 'Alert timestamp', 'suspended-order-notifier' ),
            ),
            'refund' => array(
                '{order_id}'       => __( 'Order ID', 'suspended-order-notifier' ),
                '{customer_name}'  => __( 'Customer name', 'suspended-order-notifier' ),
                '{customer_email}' => __( 'Customer email', 'suspended-order-notifier' ),
                '{refund_amount}'  => __( 'Refund amount', 'suspended-order-notifier' ),
                '{refund_reason}'  => __( 'Refund reason', 'suspended-order-notifier' ),
                '{order_total}'    => __( 'Original order total', 'suspended-order-notifier' ),
                '{refund_date}'    => __( 'Refund date', 'suspended-order-notifier' ),
            ),
            'abandoned_cart' => array(
                '{customer_name}'  => __( 'Customer name', 'suspended-order-notifier' ),
                '{customer_email}' => __( 'Customer email', 'suspended-order-notifier' ),
                '{customer_phone}' => __( 'Customer phone', 'suspended-order-notifier' ),
                '{cart_items}'     => __( 'Cart items list', 'suspended-order-notifier' ),
                '{cart_total}'     => __( 'Cart total value', 'suspended-order-notifier' ),
                '{items_count}'    => __( 'Number of items in cart', 'suspended-order-notifier' ),
                '{abandoned_time}' => __( 'Time cart was abandoned', 'suspended-order-notifier' ),
                '{cart_url}'       => __( 'Cart page URL', 'suspended-order-notifier' ),
                '{checkout_url}'   => __( 'Checkout page URL', 'suspended-order-notifier' ),
            ),
        );

        $placeholders = $type_placeholders[ $type ] ?? $type_placeholders['order'];

        return array_merge( $placeholders, $common );
    }

    /**
     * Validate a template string.
     *
     * @param string $template Template to validate.
     * @return array Validation result with 'valid' and 'errors' keys.
     */
    public function validate_template( $template ) {
        $errors = array();

        if ( empty( trim( $template ) ) ) {
            $errors[] = __( 'Template cannot be empty.', 'suspended-order-notifier' );
        }

        if ( strlen( $template ) > 4096 ) {
            $errors[] = __( 'Template exceeds maximum length of 4096 characters.', 'suspended-order-notifier' );
        }

        // Check for potentially harmful content.
        if ( preg_match( '/<script|javascript:|on\w+=/i', $template ) ) {
            $errors[] = __( 'Template contains potentially unsafe content.', 'suspended-order-notifier' );
        }

        return array(
            'valid'  => empty( $errors ),
            'errors' => $errors,
        );
    }

    /**
     * Preview a template with sample data.
     *
     * @param string $type     Notification type.
     * @param string $template Template to preview.
     * @return string Rendered preview.
     */
    public function preview_template( $type, $template = '' ) {
        if ( empty( $template ) ) {
            $template = $this->get_template( $type );
            if ( empty( $template ) ) {
                $template = $this->get_default_template( $type );
            }
        }

        $sample_data = $this->get_sample_data( $type );
        return $this->render_placeholders( $template, $sample_data );
    }

    /**
     * Get sample data for template preview.
     *
     * @param string $type Notification type.
     * @return array
     */
    private function get_sample_data( $type ) {
        $common = array(
            'site_name'    => get_bloginfo( 'name' ),
            'site_url'     => home_url(),
            'current_time' => current_time( 'Y-m-d H:i:s' ),
            'current_date' => current_time( 'Y-m-d' ),
        );

        $samples = array(
            'order' => array(
                'order_id'         => '1234',
                'order_number'     => '1234',
                'customer_name'    => 'Rajesh Kumar',
                'customer_email'   => 'rajesh@example.com',
                'customer_phone'   => '+919876543210',
                'order_items'      => "• iPhone 15 Pro x1 — ₹1,29,999\n• AirPods Pro x1 — ₹24,999",
                'order_total'      => '₹1,54,998',
                'payment_method'   => 'UPI / PhonePe',
                'shipping_method'  => 'Express Delivery',
                'shipping_address' => '123 MG Road, Bengaluru, Karnataka 560001',
                'billing_address'  => '123 MG Road, Bengaluru, Karnataka 560001',
                'order_date'       => current_time( 'Y-m-d H:i:s' ),
                'order_status'     => 'Processing',
                'order_notes'      => 'Please deliver before 5 PM',
                'items_count'      => '2',
            ),
            'order_status' => array(
                'order_id'      => '1234',
                'customer_name' => 'Rajesh Kumar',
                'old_status'    => 'Processing',
                'new_status'    => 'Completed',
                'order_total'   => '₹1,54,998',
            ),
            'stock' => array(
                'product_name'   => 'Premium Wireless Headphones',
                'product_id'     => '567',
                'product_sku'    => 'WH-PRO-100',
                'stock_quantity' => '3',
                'stock_status'   => 'LOW STOCK',
                'alert_type'     => 'low',
                'alert_time'     => current_time( 'Y-m-d H:i:s' ),
            ),
            'refund' => array(
                'order_id'       => '1234',
                'customer_name'  => 'Priya Sharma',
                'customer_email' => 'priya@example.com',
                'refund_amount'  => '₹24,999',
                'refund_reason'  => 'Product not as described',
                'order_total'    => '₹1,54,998',
                'refund_date'    => current_time( 'Y-m-d H:i:s' ),
            ),
            'abandoned_cart' => array(
                'customer_name'  => 'Amit Patel',
                'customer_email' => 'amit@example.com',
                'customer_phone' => '+919876543210',
                'cart_items'     => "• MacBook Air M2 x1 — ₹1,14,999\n• Magic Mouse x1 — ₹7,999",
                'cart_total'     => '₹1,22,998',
                'items_count'    => '2',
                'abandoned_time' => current_time( 'Y-m-d H:i:s' ),
                'cart_url'       => home_url( '/cart/' ),
                'checkout_url'   => home_url( '/checkout/' ),
            ),
        );

        $data = $samples[ $type ] ?? $samples['order'];
        return array_merge( $data, $common );
    }
}
