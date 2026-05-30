<?php
/**
 * Twilio WhatsApp API Provider.
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
 * Twilio WhatsApp integration.
 *
 * Uses Twilio's WhatsApp Business API for sending messages.
 *
 * @since 1.0.0
 */
class Twilio implements ProviderInterface {

    /**
     * Twilio API base URL.
     *
     * @var string
     */
    private $api_base = 'https://api.twilio.com/2010-04-01';

    /**
     * Account SID.
     *
     * @var string
     */
    private $account_sid;

    /**
     * Auth Token.
     *
     * @var string
     */
    private $auth_token;

    /**
     * Twilio WhatsApp sender number.
     *
     * @var string
     */
    private $from_number;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->account_sid = get_option( 'won_twilio_account_sid', '' );
        $this->auth_token  = get_option( 'won_twilio_auth_token', '' );
        $this->from_number = get_option( 'won_twilio_from_number', '' );
    }

    /**
     * Get the provider identifier.
     *
     * @return string
     */
    public function get_id() {
        return 'twilio';
    }

    /**
     * Get the provider display name.
     *
     * @return string
     */
    public function get_name() {
        return __( 'Twilio WhatsApp', 'suspended-order-notifier' );
    }

    /**
     * Check if the provider is configured.
     *
     * @return bool
     */
    public function is_configured() {
        return ! empty( $this->account_sid )
            && ! empty( $this->auth_token )
            && ! empty( $this->from_number );
    }

    /**
     * Send a WhatsApp message via Twilio.
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
                'error'      => __( 'Twilio is not configured. Please add your Account SID, Auth Token, and WhatsApp number.', 'suspended-order-notifier' ),
            );
        }

        $phone_number = $this->format_phone_number( $phone_number );
        $from_number  = $this->format_whatsapp_number( $this->from_number );

        // Build request.
        $url = sprintf(
            '%s/Accounts/%s/Messages.json',
            $this->api_base,
            $this->account_sid
        );

        $body = array(
            'From' => 'whatsapp:' . $from_number,
            'To'   => 'whatsapp:' . $phone_number,
            'Body' => $message,
        );

        // Add media URL if provided.
        if ( ! empty( $options['media_url'] ) ) {
            $body['MediaUrl'] = esc_url_raw( $options['media_url'] );
        }

        // Use content SID for templates if provided.
        if ( ! empty( $options['content_sid'] ) ) {
            $body['ContentSid'] = sanitize_text_field( $options['content_sid'] );
            unset( $body['Body'] );

            if ( ! empty( $options['content_variables'] ) ) {
                $body['ContentVariables'] = wp_json_encode( $options['content_variables'] );
            }
        }

        $response = wp_remote_post(
            $url,
            array(
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode( $this->account_sid . ':' . $this->auth_token ),
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                ),
                'body'    => $body,
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
                'message' => __( 'Please provide Account SID, Auth Token, and From Number.', 'suspended-order-notifier' ),
            );
        }

        // Test credentials by fetching account details.
        $url = sprintf(
            '%s/Accounts/%s.json',
            $this->api_base,
            $this->account_sid
        );

        $response = wp_remote_get(
            $url,
            array(
                'timeout' => 15,
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode( $this->account_sid . ':' . $this->auth_token ),
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

        if ( 200 === $code && ! empty( $body['friendly_name'] ) ) {
            return array(
                'success' => true,
                'message' => sprintf(
                    /* translators: %s: Twilio account friendly name */
                    __( 'Connected successfully! Account: %s', 'suspended-order-notifier' ),
                    $body['friendly_name']
                ),
            );
        }

        return array(
            'success' => false,
            'message' => $body['message'] ?? __( 'Failed to validate credentials.', 'suspended-order-notifier' ),
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
                'id'          => 'won_twilio_account_sid',
                'label'       => __( 'Account SID', 'suspended-order-notifier' ),
                'type'        => 'text',
                'description' => __( 'Your Twilio Account SID from the Twilio Console.', 'suspended-order-notifier' ),
                'required'    => true,
            ),
            array(
                'id'          => 'won_twilio_auth_token',
                'label'       => __( 'Auth Token', 'suspended-order-notifier' ),
                'type'        => 'password',
                'description' => __( 'Your Twilio Auth Token from the Twilio Console.', 'suspended-order-notifier' ),
                'required'    => true,
            ),
            array(
                'id'          => 'won_twilio_from_number',
                'label'       => __( 'WhatsApp Sender Number', 'suspended-order-notifier' ),
                'type'        => 'text',
                'description' => __( 'Your Twilio WhatsApp-enabled phone number (e.g., +14155238886).', 'suspended-order-notifier' ),
                'required'    => true,
                'placeholder' => '+14155238886',
            ),
        );
    }

    /**
     * Parse the Twilio API response.
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

        if ( in_array( $code, array( 200, 201 ), true ) && ! empty( $body['sid'] ) ) {
            return array(
                'success'    => true,
                'message_id' => $body['sid'],
                'error'      => null,
            );
        }

        $error_message = $body['message'] ?? __( 'Unknown Twilio error occurred.', 'suspended-order-notifier' );

        if ( ! empty( $body['more_info'] ) ) {
            $error_message .= ' (' . $body['more_info'] . ')';
        }

        return array(
            'success'    => false,
            'message_id' => null,
            'error'      => $error_message,
        );
    }

    /**
     * Format phone number for Twilio.
     *
     * @param string $phone Phone number.
     * @return string Formatted with + prefix.
     */
    private function format_phone_number( $phone ) {
        $phone = preg_replace( '/[^0-9+]/', '', $phone );

        if ( strpos( $phone, '+' ) !== 0 ) {
            $phone = '+' . ltrim( $phone, '0' );
        }

        return $phone;
    }

    /**
     * Format the WhatsApp sender number.
     *
     * @param string $number Phone number.
     * @return string Formatted number.
     */
    private function format_whatsapp_number( $number ) {
        $number = preg_replace( '/[^0-9+]/', '', $number );

        if ( strpos( $number, '+' ) !== 0 ) {
            $number = '+' . $number;
        }

        return $number;
    }
}
