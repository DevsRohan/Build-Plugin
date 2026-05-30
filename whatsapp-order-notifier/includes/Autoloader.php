<?php
/**
 * Autoloader for WhatsApp Order Notifier.
 *
 * @package suspended_Order_Notifier
 * @since 1.0.0
 */

namespace suspended_Order_Notifier;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PSR-4 compliant autoloader.
 *
 * @since 1.0.0
 */
class Autoloader {

    /**
     * Namespace prefix.
     *
     * @var string
     */
    private static $namespace_prefix = 'suspended_Order_Notifier\\';

    /**
     * Base directory for the namespace prefix.
     *
     * @var string
     */
    private static $base_dir = '';

    /**
     * Register the autoloader.
     *
     * @return void
     */
    public static function register() {
        self::$base_dir = WON_PLUGIN_DIR . 'includes/';
        spl_autoload_register( array( __CLASS__, 'autoload' ) );
    }

    /**
     * Autoload a class.
     *
     * @param string $class The fully-qualified class name.
     * @return void
     */
    public static function autoload( $class ) {
        // Check if the class uses the namespace prefix.
        $len = strlen( self::$namespace_prefix );
        if ( strncmp( self::$namespace_prefix, $class, $len ) !== 0 ) {
            return;
        }

        // Get the relative class name.
        $relative_class = substr( $class, $len );

        // Replace namespace separators with directory separators.
        $file = self::$base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

        // If the file exists, require it.
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
}
