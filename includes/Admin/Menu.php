<?php
/**
 * Admin menu registration for the WisdomRain CPT Importer plugin.
 */

namespace WisdomRain\CptImporter\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles the admin menu and page rendering.
 */
class Menu {
    /**
     * Hook into WordPress to register the menu.
     */
    public function register(): void {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
    }

    /**
     * Register the WisdomRain top-level menu and CPT Importer submenu.
     */
    public function add_menu(): void {
        $capability = 'manage_options';
        $slug       = 'wr-cpt-importer';

        add_menu_page(
            __( 'WisdomRain', 'wisdomrain-cpt-importer' ),
            __( 'WisdomRain', 'wisdomrain-cpt-importer' ),
            $capability,
            $slug,
            [ $this, 'render_page' ],
            'dashicons-cloud-upload',
            56
        );

        add_submenu_page(
            $slug,
            __( 'CPT Importer', 'wisdomrain-cpt-importer' ),
            __( 'CPT Importer', 'wisdomrain-cpt-importer' ),
            $capability,
            $slug,
            [ $this, 'render_page' ]
        );
    }

    /**
     * Render the CPT Importer admin page.
     */
    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'wisdomrain-cpt-importer' ) );
        }
        require_once WR_CPT_IMPORTER_PATH . 'admin/importer-page.php';
    }
}
