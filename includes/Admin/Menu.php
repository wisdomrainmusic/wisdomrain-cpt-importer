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

        $notice = '';

        if ( isset( $_POST['wr_cpt_importer_nonce'] ) ) {
            $notice = $this->handle_upload();
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'CPT Importer', 'wisdomrain-cpt-importer' ); ?></h1>
            <?php if ( $notice ) : ?>
                <div class="notice notice-info is-dismissible">
                    <p><?php echo esc_html( $notice ); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'wr_cpt_importer_upload', 'wr_cpt_importer_nonce' ); ?>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="wr_cpt_import_file"><?php esc_html_e( 'CSV File', 'wisdomrain-cpt-importer' ); ?></label>
                            </th>
                            <td>
                                <input type="file" id="wr_cpt_import_file" name="wr_cpt_import_file" accept=".csv" />
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button( __( 'Start Import', 'wisdomrain-cpt-importer' ) ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Handle CSV upload submissions.
     */
    private function handle_upload(): string {
        check_admin_referer( 'wr_cpt_importer_upload', 'wr_cpt_importer_nonce' );

        if ( empty( $_FILES['wr_cpt_import_file']['tmp_name'] ) ) {
            return __( 'Please choose a CSV file to upload.', 'wisdomrain-cpt-importer' );
        }

        $file = $_FILES['wr_cpt_import_file'];
        $uploaded = wp_handle_upload( $file, [ 'test_form' => false ] );

        if ( isset( $uploaded['error'] ) ) {
            return sprintf(
                /* translators: %s: upload error message */
                __( 'Upload failed: %s', 'wisdomrain-cpt-importer' ),
                $uploaded['error']
            );
        }

        return sprintf(
            /* translators: %s: uploaded file name */
            __( 'File %s uploaded successfully. Import processing will run next.', 'wisdomrain-cpt-importer' ),
            isset( $uploaded['file'] ) ? wp_basename( $uploaded['file'] ) : $file['name']
        );
    }
}
