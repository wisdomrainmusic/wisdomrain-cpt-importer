<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once WR_CPT_IMPORTER_PATH . 'includes/class-taxonomy-mapper.php';

class WR_CPT_Import_Runner {
    public function parse_csv( $file_path ) {

        if ( ! file_exists( $file_path ) ) {
            return [ 'error' => 'CSV file not found.' ];
        }

        $rows   = [];
        $header = [];

        $mapper         = new WR_CPT_Mapper();

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
        // CONTENT BUILDER (Block-based)
        // ---------------------------

        /**
         * NEW SYSTEM — Gutenberg Block-Based Content Builder
         * Creates 3 blocks:
         * 1) Paragraph block → product_description
         * 2) Shortcode block → audio_shorth_code
         * 3) Shortcode block → pdf_player_shorth_code
         */

        $blocks = [];

        // 1) Description Block
        if ( ! empty( $row['product_description'] ) ) {
            $blocks[] = "<!-- wp:paragraph -->{$row['product_description']}<!-- /wp:paragraph -->";
        }

        // 2) Audio Player Shortcode Block
        if ( ! empty( $row['audio_shorth_code'] ) ) {
            $audio     = trim( $row['audio_shorth_code'] );
            $blocks[] = "<!-- wp:shortcode -->{$audio}<!-- /wp:shortcode -->";
        }

        // 3) PDF Player Shortcode Block
        if ( ! empty( $row['pdf_player_shorth_code'] ) ) {
            $pdf     = trim( $row['pdf_player_shorth_code'] );
            $blocks[] = "<!-- wp:shortcode -->{$pdf}<!-- /wp:shortcode -->";
        }

        return implode( "\n\n", $blocks );
    }

    /**
     * Create a new post from a prepared CSV row.
     */
    public function create_post_from_row( $row ) {

        $title           = wr_clean( $row['product_title'] ?? '' );
        $description     = wr_clean( $row['product_description'] ?? '' );
        $short_desc      = wr_clean( $row['short_description'] ?? '' );

        $audio_shortcode = trim( $row['audio_shorth_code'] ?? '' );
        $pdf_shortcode   = trim( $row['pdf_player_shorth_code'] ?? '' );

        $seo_title     = sanitize_text_field( $row['seo_title'] ?? '' );
        $seo_desc      = sanitize_text_field( $row['seo_description'] ?? '' );
        $focus_keyword = sanitize_text_field( $row['focus_keyword'] ?? '' );

        $group_id = sanitize_text_field( $row['group_id'] ?? '' );

        $status = ! empty( $row['status'] ) ? sanitize_text_field( $row['status'] ) : 'draft';

        // 1) CPT type
        $mapper    = new WR_CPT_Mapper();
        $post_type = $mapper->resolve_post_type( $row['cpt_taxonomy'] );

        if ( ! $post_type ) {
            return [ 'error' => 'Unknown CPT type: ' . $row['cpt_taxonomy'] ];
        }

        // 2) Slug override
        $post_slug = ! empty( $row['slug'] ) ? sanitize_title( $row['slug'] ) : sanitize_title( $title );

        // 3) Construct content (without inline featured image)
        $content = $this->build_content( [
            'product_description'    => $description,
            'audio_shorth_code'      => $audio_shortcode,
            'pdf_player_shorth_code' => $pdf_shortcode,
        ] );

        // 4) Insert or update post if group_id exists
        $existing = get_posts( [
            'post_type'      => $post_type,
            'posts_per_page' => 1,
            'meta_key'       => 'group_id',
            'meta_value'     => $group_id,
        ] );

        if ( $existing ) {
            $post_id = $existing[0]->ID;

            $update_result = wp_update_post( [
                'ID'           => $post_id,
                'post_title'   => $title,
                'post_content' => $content,
                'post_status'  => $status,
            ], true );

            if ( is_wp_error( $update_result ) ) {
                return [ 'error' => $update_result->get_error_message() ];
            }
        } else {
            $post_id = wp_insert_post( [
                'post_type'    => $post_type,
                'post_title'   => $title,
                'post_content' => $content,
                'post_status'  => $status,
                'post_name'    => $post_slug,
            ], true );

            if ( is_wp_error( $post_id ) ) {
                return [ 'error' => $post_id->get_error_message() ];
            }
        }

        // 5) Featured image download & attach
        if ( ! empty( $row['product_image'] ) ) {
            $image_url = esc_url_raw( $row['product_image'] );
            $image_id  = media_sideload_image( $image_url, $post_id, null, 'id' );

            if ( ! is_wp_error( $image_id ) ) {
                set_post_thumbnail( $post_id, $image_id );
            }
        }

        // 6) Taxonomies
        if ( ! empty( $row['_taxonomy_terms'] ) ) {
            wp_set_object_terms( $post_id, $row['_taxonomy_terms'], $mapper->get_taxonomy_for_post_type( $post_type ) );
        }

        // 7) Meta fields
        update_post_meta( $post_id, 'group_id', $group_id );
        update_post_meta( $post_id, 'buy_link', $row['buy_link'] );

        // RankMath fields
        update_post_meta( $post_id, 'rank_math_focus_keyword', $focus_keyword );
        update_post_meta( $post_id, 'rank_math_title', $seo_title );
        update_post_meta( $post_id, 'rank_math_description', $seo_desc );

        return [
            'success' => true,
            'post_id' => $post_id,
        ];
    }
}
