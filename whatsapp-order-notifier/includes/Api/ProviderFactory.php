<?php
/**
 * API Provider Factory.
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
 * Factory class for creating API provider instances.
 *
 * @since 1.0.0
 */
class ProviderFactory {

    /**
     * Registered providers.
     *
     * @var array
     */
    private static $providers = array();

    /**
     * Get an API provider instance.
     *
     * @param string $provider_id Provider identifier. If empty, uses the configured default.
     * @return ProviderInterface
     * @throws \InvalidArgumentException If provider is not found.
     */
    public static function get_provider( $provider_id = '' ) {
        if ( empty( $provider_id ) ) {
            $provider_id = get_option( 'won_api_provider', 'whatsapp_business' );
        }

        if ( isset( self::$providers[ $provider_id ] ) ) {
            return self::$providers[ $provider_id ];
        }

        $provider = self::create_provider( $provider_id );
        self::$providers[ $provider_id ] = $provider;

        return $provider;
    }

    /**
     * Create a provider instance.
     *
     * @param string $provider_id Provider identifier.
     * @return ProviderInterface
     * @throws \InvalidArgumentException If provider is not recognized.
     */
    private static function create_provider( $provider_id ) {
        switch ( $provider_id ) {
            case 'whatsapp_business':
                return new WhatsAppBusiness();

            case 'twilio':
                return new Twilio();

            default:
                /**
                 * Filter to allow third-party providers to be registered.
                 *
                 * @param ProviderInterface|null $provider Provider instance.
                 * @param string                 $provider_id Provider identifier.
                 */
                $provider = apply_filters( 'won_api_provider_instance', null, $provider_id );

                if ( $provider instanceof ProviderInterface ) {
                    return $provider;
                }

                throw new \InvalidArgumentException(
                    sprintf(
                        /* translators: %s: provider ID */
                        __( 'Unknown API provider: %s', 'suspended-order-notifier' ),
                        $provider_id
                    )
                );
        }
    }

    /**
     * Get all available providers.
     *
     * @return array Array of provider info arrays.
     */
    public static function get_available_providers() {
        $providers = array(
            'whatsapp_business' => array(
                'id'          => 'whatsapp_business',
                'name'        => __( 'WhatsApp Business Cloud API', 'suspended-order-notifier' ),
                'description' => __( 'Official Meta WhatsApp Business API. Free tier available. Recommended for most users.', 'suspended-order-notifier' ),
                'icon'        => 'whatsapp',
                'free_tier'   => true,
            ),
            'twilio' => array(
                'id'          => 'twilio',
                'name'        => __( 'Twilio WhatsApp', 'suspended-order-notifier' ),
                'description' => __( 'Twilio\'s WhatsApp Business API. Pay-per-message pricing. Great for high-volume senders.', 'suspended-order-notifier' ),
                'icon'        => 'twilio',
                'free_tier'   => false,
            ),
        );

        /**
         * Filter available API providers.
         *
         * @param array $providers Available providers.
         */
        return apply_filters( 'won_available_providers', $providers );
    }

    /**
     * Get settings fields for a specific provider.
     *
     * @param string $provider_id Provider identifier.
     * @return array
     */
    public static function get_provider_settings( $provider_id ) {
        try {
            $provider = self::get_provider( $provider_id );
            return $provider->get_settings_fields();
        } catch ( \InvalidArgumentException $e ) {
            return array();
        }
    }

    /**
     * Test connection for the current provider.
     *
     * @param string $provider_id Optional provider ID.
     * @return array
     */
    public static function test_connection( $provider_id = '' ) {
        try {
            $provider = self::get_provider( $provider_id );
            return $provider->validate_credentials();
        } catch ( \InvalidArgumentException $e ) {
            return array(
                'success' => false,
                'message' => $e->getMessage(),
            );
        }
    }

    /**
     * Send a message using the configured provider.
     *
     * @param string $phone   Recipient phone.
     * @param string $message Message content.
     * @param array  $options Additional options.
     * @return array
     */
    public static function send( $phone, $message, $options = array() ) {
        try {
            $provider = self::get_provider();
            return $provider->send_message( $phone, $message, $options );
        } catch ( \InvalidArgumentException $e ) {
            return array(
                'success'    => false,
                'message_id' => null,
                'error'      => $e->getMessage(),
            );
        }
    }
}
