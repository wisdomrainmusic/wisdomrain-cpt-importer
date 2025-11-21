<?php
/**
 * Plugin Name: WisdomRain CPT Importer
 * Plugin URI: https://example.com/wisdomrain-cpt-importer
 * Description: Import custom post types with WisdomRain tools.
 * Version: 0.1.0
 * Author: WisdomRain
 * License: GPL-2.0-or-later
 * Text Domain: wisdomrain-cpt-importer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants.
define( 'WR_CPT_IMPORTER_VERSION', '0.1.0' );
define( 'WR_CPT_IMPORTER_FILE', __FILE__ );
define( 'WR_CPT_IMPORTER_PATH', plugin_dir_path( WR_CPT_IMPORTER_FILE ) );
require_once WR_CPT_IMPORTER_PATH . 'includes/autoloader.php';
require_once WR_CPT_IMPORTER_PATH . 'includes/Plugin.php';

if ( ! function_exists( 'wr_clean' ) ) {
    /**
     * Clean CSV-derived values.
     *
     * @param mixed $value Raw value from CSV.
     *
     * @return string Sanitized string value.
     */
    function wr_clean( $value ) {
        if ( ! $value ) {
            return '';
        }

        return trim( wp_kses_post( $value ) );
    }
}

// Initialize plugin.
\WisdomRain\CptImporter\Plugin::init();
