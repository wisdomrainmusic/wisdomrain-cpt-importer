<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$upload_notice = '';

if ( isset( $_POST['wr_cpt_importer_nonce'] ) ) {
    check_admin_referer( 'wr_cpt_importer_upload', 'wr_cpt_importer_nonce' );

    if ( empty( $_FILES['csv_file']['tmp_name'] ) ) {
        $upload_notice = __( 'Please choose a CSV file to upload.', 'wisdomrain-cpt-importer' );
    } else {
        if ( ! empty( $_FILES['csv_file']['tmp_name'] ) ) {

            $tmp = $_FILES['csv_file']['tmp_name'];

            require_once WR_CPT_IMPORTER_PATH . 'includes/class-import-runner.php';

            $runner = new WR_CPT_Import_Runner();

            $result = $runner->parse_csv( $tmp );

            if ( ! empty( $result['error'] ) ) {
                echo '<div class="notice notice-error"><p><strong>CSV ERROR:</strong> ' . esc_html( $result['error'] ) . '</p></div>';
            } else {
                echo '<div class="notice notice-success"><p>CSV Loaded: ' . esc_html( $result['total'] ) . ' rows found.</p></div>';

                if ( ! empty( $result['rows'] ) ) {
                    $image_handler = new WR_CPT_Image_Handler();

                    foreach ( $result['rows'] as $row ) {

                        // 1) Image download (from previous commit)
                        $row['_image_id'] = $image_handler->download_and_attach( trim( $row['product_image'] ), 0 );

                        // 2) Taxonomy + content + post insert
                        $created = $runner->create_post_from_row( $row );

                        if ( ! empty( $created['error'] ) ) {
                            echo '<div class="notice notice-error"><p>Error: ' . esc_html( $created['error'] ) . '</p></div>';
                        } else {
                            echo '<div class="notice notice-success"><p>Post Created: ID #' . intval( $created['post_id'] ) . '</p></div>';
                        }
                    }
                }
            }
        }
    }
}
?>
<div class="wrap">
    <h1><?php esc_html_e( 'CPT Importer', 'wisdomrain-cpt-importer' ); ?></h1>
    <?php if ( $upload_notice ) : ?>
        <div class="notice notice-info is-dismissible">
            <p><?php echo esc_html( $upload_notice ); ?></p>
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
                        <input type="file" id="wr_cpt_import_file" name="csv_file" accept=".csv" />
                    </td>
                </tr>
            </tbody>
        </table>
        <?php submit_button( __( 'Start Import', 'wisdomrain-cpt-importer' ) ); ?>
    </form>
</div>
