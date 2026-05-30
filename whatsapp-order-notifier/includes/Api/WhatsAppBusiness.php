<?php
/**
 * WhatsApp Business API Provider.
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
 * WhatsApp Business Cloud API integration.
 *
 * Uses the official Meta WhatsApp Business Cloud API.
 *
 * @since 1.0.0
 */
class WhatsAppBusiness implements ProviderInterface {

    /**
     * API base URL.
     *
     * @var string
     */
    private $api_base = 'https://graph.facebook.com/v18.0';

    /**
     * Access token.
     *
     * @var string
     */
    private $access_token;

    /**
     * Phone number ID.
     *
     * @var string
     */
    private $phone_number_id;

    /**
     * Business Account ID.
     *
     * @var string
     */
    private $business_account_id;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->access_token        = get_option( 'won_whatsapp_access_token', '' );
        $this->phone_number_id     = get_option( 'won_whatsapp_phone_number_id', '' );
        $this->business_account_id = get_option( 'won_whatsapp_business_account_id', '' );
    }

    /**
     * Get the provider identifier.
     *
     * @return string
     */
    public function get_id() {
        return 'whatsapp_business';
    }

    /**
     * Get the provider display name.
     *
     * @return string
     */
    public function get_name() {
        return __( 'WhatsApp Business Cloud API', 'suspended-order-notifier' );
    }

    /**
     * Check if the provider is configured.
     *
     * @return bool
     */
    public function is_configured() {
        return ! empty( $this->access_token ) && ! empty( $this->phone_number_id );
    }

    /**
     * Send a WhatsApp message via the Business Cloud API.
     *
     * @param string $phone_number Recipient phone number.
     * @param string $message      Message content.
     * @param array  $options      Additional options.
     * @return array
     */
    public function send_message( $phone_number, $message, $options = array() ) {
        if ( ! $this->is_configured() ) {
            return array(
                'success'    => false,
                'message_id' => null,
                'error'      => __( 'WhatsApp Business API is not configured. Please add your access token and phone number ID.', 'suspended-order-notifier' ),
            );
        }

        $phone_number = $this->sanitize_phone_number( $phone_number );

        // Build the request body.
        $body = array(
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $phone_number,
        );

        // Check if this is a template message or a text message.
        if ( ! empty( $options['template_name'] ) ) {
            $body['type'] = 'template';
            $body['template'] = array(
                'name'     => sanitize_text_field( $options['template_name'] ),
                'language' => array(
                    'code' => $options['language_code'] ?? 'en',
                ),
            );

            if ( ! empty( $options['template_parameters'] ) ) {
                $body['template']['components'] = $options['template_parameters'];
            }
        } else {
            $body['type'] = 'text';
            $body['text'] = array(
                'preview_url' => false,
                'body'        => $message,
            );
        }

        // Make the API request.
        $url = sprintf(
            '%s/%s/messages',
            $this->api_base,
            $this->phone_number_id
        );

        $response = wp_remote_post(
            $url,
            array(
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->access_token,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode( $body ),
            )
        );

        return $this->parse_response( $response );
    }

    /**
     * Validate the API credentials.
     *
     * @return array
     */
    public function validate_credentials() {
        if ( ! $this->is_configured() ) {
            return array(
                'success' => false,
                'message' => __( 'Please provide both Access Token and Phone Number ID.', 'suspended-order-notifier' ),
            );
        }

        // Test the credentials by fetching the phone number details.
        $url = sprintf(
            '%s/%s',
            $this->api_base,
            $this->phone_number_id
        );

        $response = wp_remote_get(
            $url,
            array(
                'timeout' => 15,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->access_token,
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
            );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 === $code && ! empty( $body['verified_name'] ) ) {
            return array(
                'success' => true,
                'message' => sprintf(
                    /* translators: %s: verified business name */
                    __( 'Connected successfully! Verified name: %s', 'suspended-order-notifier' ),
                    $body['verified_name']
                ),
            );
        }

        $error_message = $body['error']['message'] ?? __( 'Unknown error occurred.', 'suspended-order-notifier' );

        return array(
            'success' => false,
            'message' => $error_message,
        );
    }

    /**
     * Get settings fields for this provider.
     *
     * @return array
     */
    public function get_settings_fields() {
        return array(
            array(
                'id'          => 'won_whatsapp_access_token',
                'label'       => __( 'Access Token', 'suspended-order-notifier' ),
                'type'        => 'password',
                'description' => __( 'Your WhatsApp Business API permanent access token from Meta Developer Portal.', 'suspended-order-notifier' ),
                'required'    => true,
            ),
            array(
                'id'          => 'won_whatsapp_phone_number_id',
                'label'       => __( 'Phone Number ID', 'suspended-order-notifier' ),
                'type'        => 'text',
                'description' => __( 'The Phone Number ID from your WhatsApp Business account.', 'suspended-order-notifier' ),
                'required'    => true,
            ),
            array(
                'id'          => 'won_whatsapp_business_account_id',
                'label'       => __( 'Business Account ID', 'suspended-order-notifier' ),
                'type'        => 'text',
                'description' => __( 'Your WhatsApp Business Account ID (optional, used for template management).', 'suspended-order-notifier' ),
                'required'    => false,
            ),
            array(
                'id'          => 'won_whatsapp_webhook_verify_token',
                'label'       => __( 'Webhook Verify Token', 'suspended-order-notifier' ),
                'type'        => 'text',
                'description' => __( 'A custom token for webhook verification. Set this same value in your Meta app webhook settings.', 'suspended-order-notifier' ),
                'required'    => false,
            ),
        );
    }

    /**
     * Parse the API response.
     *
     * @param array|\WP_Error $response API response.
     * @return array Normalized response.
     */
    private function parse_response( $response ) {
        if ( is_wp_error( $response ) ) {
            return array(
                'success'    => false,
                'message_id' => null,
                'error'      => $response->get_error_message(),
            );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( in_array( $code, array( 200, 201 ), true ) && ! empty( $body['messages'][0]['id'] ) ) {
            return array(
                'success'    => true,
                'message_id' => $body['messages'][0]['id'],
                'error'      => null,
            );
        }

        // Parse error message.
        $error_message = __( 'Unknown error occurred.', 'suspended-order-notifier' );

        if ( ! empty( $body['error']['message'] ) ) {
            $error_message = $body['error']['message'];

            if ( ! empty( $body['error']['error_data']['details'] ) ) {
                $error_message .= ' - ' . $body['error']['error_data']['details'];
            }
        }

        return array(
            'success'    => false,
            'message_id' => null,
            'error'      => $error_message,
        );
    }

    /**
     * Sanitize and format phone number for WhatsApp API.
     *
     * @param string $phone Phone number.
     * @return string Formatted phone number.
     */
    private function sanitize_phone_number( $phone ) {
        // Remove all non-numeric characters except leading +.
        $phone = preg_replace( '/[^0-9+]/', '', $phone );

        // Remove leading + if present (API expects just numbers).
        $phone = ltrim( $phone, '+' );

        // Remove leading zeros.
        $phone = ltrim( $phone, '0' );

        return $phone;
    }
}
