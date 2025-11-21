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
            'pdf_player_shorth_code' => '',
        ];

        $row = wp_parse_args( $row, $defaults );

        // ---------------------------
        // CONTENT BUILDER (FINAL V2)
        // ---------------------------

        $content  = '';

        // Title (WordPress zaten title olarak kaydediyor, o yüzden sadece içerik istiyorsak kapatabiliriz)
        // $content .= '<h1>' . esc_html($row['product_title']) . '</h1>' . "\n\n";

        // Product Description
        if ( ! empty( $row['product_description'] ) ) {
            $content .= wp_kses_post( $row['product_description'] ) . "\n\n";
        }

        // Spacing before players (prevent overlay under featured image)
        $content .= "<div style='margin-top:60px'></div>\n\n";

        // AUDIO PLAYER SHORTCODE
        if ( ! empty( $row['audio_shorth_code'] ) ) {
            $content .= trim( $row['audio_shorth_code'] ) . "\n\n";
        }

        // Extra spacing before PDF player
        $content .= "<div style='margin-top:40px'></div>\n\n";

        // PDF PLAYER SHORTCODE – ✔ DOĞRU CSV KEY
        if ( ! empty( $row['pdf_player_shorth_code'] ) ) {
            $content .= trim( $row['pdf_player_shorth_code'] ) . "\n\n";
        }

        return $content;
    }

    /**
     * Create a new post from a prepared CSV row.
     */
    public function create_post_from_row( $row ) {

        // 1) CPT type
        $mapper    = new WR_CPT_Mapper();
        $post_type = $mapper->resolve_post_type( $row['cpt_taxonomy'] );

        if ( ! $post_type ) {
            return [ 'error' => 'Unknown CPT type: ' . $row['cpt_taxonomy'] ];
        }

        // 2) Slug override
        $post_slug = ! empty( $row['slug'] ) ? sanitize_title( $row['slug'] ) : sanitize_title( $row['product_title'] );

        // 3) Construct content (without inline featured image)
        $content = $this->build_content( $row );

        // 4) Insert post
        $post_id = wp_insert_post( [
            'post_type'    => $post_type,
            'post_title'   => sanitize_text_field( $row['product_title'] ),
            'post_content' => $content,
            'post_status'  => 'draft',
            'post_name'    => $post_slug,
        ] );

        if ( is_wp_error( $post_id ) ) {
            return [ 'error' => $post_id->get_error_message() ];
        }

        // 5) Featured image
        if ( ! empty( $row['_image_id'] ) ) {
            set_post_thumbnail( $post_id, intval( $row['_image_id'] ) );
        }

        // 6) Taxonomies
        if ( ! empty( $row['_taxonomy_terms'] ) ) {
            wp_set_object_terms( $post_id, $row['_taxonomy_terms'], $mapper->get_taxonomy_for_post_type( $post_type ) );
        }

        // 7) Meta fields
        update_post_meta( $post_id, 'group_id', $row['group_id'] );
        update_post_meta( $post_id, 'buy_link', $row['buy_link'] );

        // RankMath fields
        update_post_meta( $post_id, 'rank_math_title', $row['seo_title'] );
        update_post_meta( $post_id, 'rank_math_description', $row['short_description'] );
        update_post_meta( $post_id, 'rank_math_focus_keyword', $row['focus_keyword'] );

        return [
            'success' => true,
            'post_id' => $post_id,
        ];
    }
}
