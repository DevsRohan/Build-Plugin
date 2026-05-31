<?php
namespace WON_Trial;

if ( ! defined( 'ABSPATH' ) ) exit;

class Autoloader {
    public static function register() {
        spl_autoload_register( array( __CLASS__, 'autoload' ) );
    }

    public static function autoload( $class ) {
        $prefix = 'WON_Trial\\';
        $len = strlen( $prefix );
        if ( strncmp( $prefix, $class, $len ) !== 0 ) return;

        $relative = substr( $class, $len );
        $file = WON_TRIAL_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';

        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
}
