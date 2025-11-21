<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once WR_CPT_IMPORTER_PATH . 'includes/class-taxonomy-mapper.php';
require_once WR_CPT_IMPORTER_PATH . 'includes/class-image-handler.php';

class WR_CPT_Import_Runner {
    public function parse_csv( $file_path ) {

        if ( ! file_exists( $file_path ) ) {
            return [ 'error' => 'CSV file not found.' ];
        }

        $rows   = [];
        $header = [];

        $mapper         = new WR_CPT_Mapper();
        $image_handler  = new WR_CPT_Image_Handler();

        if ( ( $handle = fopen( $file_path, 'r' ) ) !== false ) {

            $line_number = 0;

            while ( ( $data = fgetcsv( $handle, 100000, ',' ) ) !== false ) {

                // Header row
                if ( 0 === $line_number ) {
                    $header = $data;
                } else {

                    // Skip completely empty rows
                    if ( 0 === count( array_filter( $data ) ) ) {
                        continue;
                    }

                    $row = array_combine( $header, $data );

                    $post_type = $mapper->resolve_post_type( $row['cpt_taxonomy'] );

                    if ( ! $post_type ) {
                        return [ 'error' => 'Unknown CPT Taxonomy: ' . $row['cpt_taxonomy'] ];
                    }

                    $row['post_type'] = $post_type;

                    $taxonomy = $mapper->get_taxonomy_for_post_type( $post_type );

                    if ( $taxonomy ) {

                        // Parent category
                        $parent_term_id = $mapper->get_or_create_parent_term(
                            $taxonomy,
                            trim( $row['parent_category'] )
                        );

                        // Subcategory
                        $child_term_id = $mapper->get_or_create_child_term(
                            $taxonomy,
                            $parent_term_id,
                            trim( $row['subcategory'] )
                        );

                        $terms_to_assign = [];

                        if ( $parent_term_id ) { $terms_to_assign[] = intval( $parent_term_id ); }
                        if ( $child_term_id )  { $terms_to_assign[] = intval( $child_term_id ); }

                        // Kaydetmeye hazırlıyoruz
                        $row['_taxonomy_terms'] = $terms_to_assign;
                    }

                    $attachment_id = $image_handler->download_and_attach(
                        isset( $row['product_image'] ) ? trim( $row['product_image'] ) : '',
                        0 // henüz post yok, sonradan set edeceğiz
                    );

                    $row['_image_id'] = $attachment_id;

                    $rows[] = $row;
                }

                $line_number++;
            }

            fclose( $handle );
        }

        if ( empty( $rows ) ) {
            return [ 'error' => 'CSV read successfully but contains no rows.' ];
        }

        return [
            'header' => $header,
            'rows'   => $rows,
            'total'  => count( $rows ),
        ];
    }

    /**
     * Attach downloaded image as featured image when post is created.
     */
    public function maybe_set_featured_image( $post_id, $row ) {

        if ( empty( $post_id ) || empty( $row['_image_id'] ) ) {
            return;
        }

        set_post_thumbnail( $post_id, $row['_image_id'] );
    }

    /**
     * Build the post content with title, cover image, description and shortcodes.
     */
    public function build_content( $row ): string {

        $defaults = [
            'product_title'        => '',
            'product_description'  => '',
            'audio_shorth_code'    => '',
            'pdf_player_shortcode' => '',
        ];

        $row = wp_parse_args( $row, $defaults );

        $image_html = '';

        if ( ! empty( $row['_image_id'] ) ) {
            $image_html = wp_get_attachment_image(
                $row['_image_id'],
                'large',
                false,
                [
                    'class' => 'wr-cover-image',
                ]
            );
        }

        $content  = '';
        $content .= '<h1>' . esc_html( $row['product_title'] ) . '</h1>' . "\n\n";
        $content .= $image_html . "\n\n";
        $content .= wp_kses_post( $row['product_description'] ) . "\n\n";
        $content .= $row['audio_shorth_code'] . "\n\n";
        $content .= $row['pdf_player_shortcode'] . "\n\n";

        return $content;
    }
}
