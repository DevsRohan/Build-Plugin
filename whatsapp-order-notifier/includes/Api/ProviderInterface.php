<?php
/**
 * API Provider Interface.
 *
 * @package suspended_Order_Notifier\Api
 * @since 1.0.0
 */

namespace suspended_Order_Notifier\Api;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Interface that all WhatsApp API providers must implement.
 *
 * @since 1.0.0
 */
interface ProviderInterface {

    /**
     * Get the provider identifier.
     *
     * @return string
     */
    public function get_id();

    /**
     * Get the provider display name.
     *
     * @return string
     */
    public function get_name();

    /**
     * Check if the provider is configured and ready.
     *
     * @return bool
     */
    public function is_configured();

    /**
     * Send a WhatsApp message.
     *
     * @param string $phone_number Recipient phone number with country code.
     * @param string $message      Message content.
     * @param array  $options      Additional options (media, template, etc.).
     * @return array Response with 'success', 'message_id', and 'error' keys.
     */
    public function send_message( $phone_number, $message, $options = array() );

    /**
     * Validate the provider credentials.
     *
     * @return array Response with 'success' and 'message' keys.
     */
    public function validate_credentials();

    /**
     * Get the provider settings fields.
     *
     * @return array Array of settings field definitions.
     */
    public function get_settings_fields();
}
