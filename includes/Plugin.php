<?php
/**
 * Main plugin bootstrapper.
 */

namespace WisdomRain\CptImporter;

use WisdomRain\CptImporter\Admin\Menu;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin {
    /**
     * Current plugin version.
     */
    public const VERSION = '0.1.0';

    /**
     * Singleton instance holder.
     *
     * @var Plugin|null
     */
    private static ?Plugin $instance = null;

    /**
     * Autoloader instance.
     *
     * @var Autoloader
     */
    private Autoloader $autoloader;

    /**
     * Set up the plugin.
     */
    private function __construct() {
        $this->autoloader = new Autoloader( plugin_dir_path( __DIR__ ) );
        $this->autoloader->register();

        $this->boot_admin();
    }

    /**
     * Initialize admin-only hooks and features.
     */
    private function boot_admin(): void {
        if ( is_admin() ) {
            ( new Menu() )->register();
        }
    }

    /**
     * Initialize the plugin.
     */
    public static function init(): void {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
    }
}
