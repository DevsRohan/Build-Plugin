<?php
/**
 * Hugging Face Space WhatsApp API.
 * 
 * Sends messages via the self-hosted Node.js backend on HF Spaces.
 * No paid APIs. No Twilio. No WhatsApp Business API. Just your own server.
 *
 * @package suspended_Order_Notifier\Api
 */

namespace suspended_Order_Notifier\Api;

if ( ! defined( 'ABSPATH' ) ) exit;

class HFWhatsApp {

    /**
     * Get the configured HF Space URL.
     */
    private function get_base_url() {
        $url = get_option( 'won_hf_space_url', '' );
        return rtrim( $url, '/' );
    }

    /**
     * Get API secret header.
     */
    private function get_headers() {
        $secret = get_option( 'won_api_secret', '' );
        $headers = array( 'Content-Type' => 'application/json' );
        if ( ! empty( $secret ) ) {
            $headers['x-api-secret'] = $secret;
        }
        return $headers;
    }

    /**
     * Check if the backend is configured.
     */
    public function is_configured() {
        return ! empty( $this->get_base_url() );
    }

    /**
     * Get WhatsApp connection status from HF backend.
     *
     * @return array {success, status, connected, info, messagesSent}
     */
    public function get_status() {
        if ( ! $this->is_configured() ) {
            return array( 'success' => false, 'status' => 'not_configured', 'connected' => false );
        }

        $response = wp_remote_get(
            $this->get_base_url() . '/status',
            array( 'timeout' => 10, 'headers' => $this->get_headers() )
        );

        if ( is_wp_error( $response ) ) {
            return array( 'success' => false, 'status' => 'unreachable', 'connected' => false, 'error' => $response->get_error_message() );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        return $body ?: array( 'success' => false, 'status' => 'error', 'connected' => false );
    }

    /**
     * Get QR code from HF backend for WhatsApp authentication.
     *
     * @return array {success, status, qr (base64 image)}
     */
    public function get_qr() {
        if ( ! $this->is_configured() ) {
            return array( 'success' => false, 'error' => 'HF Space URL not configured.' );
        }

        $response = wp_remote_get(
            $this->get_base_url() . '/qr',
            array( 'timeout' => 15, 'headers' => $this->get_headers() )
        );

        if ( is_wp_error( $response ) ) {
            return array( 'success' => false, 'error' => $response->get_error_message() );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        return $body ?: array( 'success' => false, 'error' => 'Invalid response from backend.' );
    }

    /**
     * Send a WhatsApp message via HF backend.
     *
     * @param string $phone   Recipient phone number.
     * @param string $message Message text.
     * @return array {success, messageId, error}
     */
    public function send_message( $phone, $message ) {
        if ( ! $this->is_configured() ) {
            return array( 'success' => false, 'messageId' => null, 'error' => 'HF Space URL not configured.' );
        }

        // Format phone: remove everything except digits
        $phone = preg_replace( '/[^0-9]/', '', $phone );

        // If starts with 0, remove it
        if ( strpos( $phone, '0' ) === 0 ) {
            $phone = substr( $phone, 1 );
        }

        // If 10 digits (no country code), prepend default country code
        if ( strlen( $phone ) === 10 ) {
            $country_code = get_option( 'won_country_code', '+91' );
            $country_code = preg_replace( '/[^0-9]/', '', $country_code );
            $phone = $country_code . $phone;
        }

        $response = wp_remote_post(
            $this->get_base_url() . '/send',
            array(
                'timeout' => 30,
                'headers' => $this->get_headers(),
                'body'    => wp_json_encode( array(
                    'phone'   => $phone,
                    'message' => $message,
                ) ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return array( 'success' => false, 'messageId' => null, 'error' => $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 200 && $code < 300 && ! empty( $body['success'] ) ) {
            return array(
                'success'   => true,
                'messageId' => $body['messageId'] ?? null,
                'error'     => null,
            );
        }

        return array(
            'success'   => false,
            'messageId' => null,
            'error'     => $body['error'] ?? 'Unknown error (HTTP ' . $code . ')',
        );
    }

    /**
     * Logout / disconnect WhatsApp session.
     */
    public function logout() {
        if ( ! $this->is_configured() ) {
            return array( 'success' => false, 'error' => 'Not configured.' );
        }

        $response = wp_remote_post(
            $this->get_base_url() . '/logout',
            array( 'timeout' => 15, 'headers' => $this->get_headers(), 'body' => '{}' )
        );

        if ( is_wp_error( $response ) ) {
            return array( 'success' => false, 'error' => $response->get_error_message() );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        return $body ?: array( 'success' => false, 'error' => 'Invalid response.' );
    }

    /**
     * Test if the HF Space is reachable (health check).
     */
    public function health_check() {
        if ( ! $this->is_configured() ) {
            return array( 'success' => false, 'error' => 'HF Space URL not configured.' );
        }

        $response = wp_remote_get(
            $this->get_base_url() . '/health',
            array( 'timeout' => 10 )
        );

        if ( is_wp_error( $response ) ) {
            return array( 'success' => false, 'error' => $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( 200 === $code ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            return array( 'success' => true, 'data' => $body );
        }

        return array( 'success' => false, 'error' => 'Backend returned HTTP ' . $code );
    }
}
