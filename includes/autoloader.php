<?php
/**
 * Simple PSR-4 autoloader for WisdomRain CPT Importer classes.
 */

namespace WisdomRain\CptImporter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Autoloader' ) ) {
    /**
     * Autoloader registration for the plugin classes.
     */
    class Autoloader {
        /**
         * Namespace prefix used by the plugin.
         */
        private const PREFIX = __NAMESPACE__ . '\\';

        /**
         * Base directory for the namespace prefix.
         *
         * @var string
         */
        private string $base_dir;

        /**
         * Initialize the autoloader with the plugin base path.
         *
         * @param string $base_dir Base directory for class files.
         */
        public function __construct( string $base_dir ) {
            $this->base_dir = trailingslashit( $base_dir );
        }

        /**
         * Register the autoload callback.
         */
        public function register(): void {
            spl_autoload_register( [ $this, 'autoload' ] );
        }

        /**
         * Callback to autoload plugin classes.
         *
         * @param string $class Fully qualified class name.
         */
        private function autoload( string $class ): void {
            if ( 0 !== strpos( $class, self::PREFIX ) ) {
                return;
            }

            $relative_class = substr( $class, strlen( self::PREFIX ) );
            $relative_path  = str_replace( '\\', '/', $relative_class );
            $file           = $this->base_dir . 'includes/' . $relative_path . '.php';

            if ( file_exists( $file ) ) {
                require_once $file;
            }
        }
    }
}
