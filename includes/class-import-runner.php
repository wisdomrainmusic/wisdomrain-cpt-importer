<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once WR_CPT_IMPORTER_PATH . 'includes/class-taxonomy-mapper.php';
require_once WR_CPT_IMPORTER_PATH . 'includes/class-image-handler.php';

class WR_CPT_Import_Runner {

    /**
     * Normalize and sanitize a raw CSV row.
     *
     * @param array $row
     * @return array
     */
    private function sanitize_row( array $row ): array {
        $clean = [];

        $clean['group_id']               = isset( $row['group_id'] ) ? sanitize_text_field( $row['group_id'] ) : '';
        $clean['product_title']          = isset( $row['product_title'] ) ? wp_kses_post( trim( $row['product_title'] ) ) : '';
        $clean['product_description']    = isset( $row['product_description'] ) ? wp_kses_post( trim( $row['product_description'] ) ) : '';
        $clean['short_description']      = isset( $row['short_description'] ) ? wp_kses_post( trim( $row['short_description'] ) ) : '';

        $clean['audio_shorth_code']      = isset( $row['audio_shorth_code'] ) ? trim( $row['audio_shorth_code'] ) : '';
        $clean['pdf_player_shorth_code'] = isset( $row['pdf_player_shorth_code'] ) ? trim( $row['pdf_player_shorth_code'] ) : '';

        $clean['seo_title']              = isset( $row['seo_title'] ) ? sanitize_text_field( $row['seo_title'] ) : '';
        $clean['seo_description']        = isset( $row['seo_description'] ) ? sanitize_text_field( $row['seo_description'] ) : '';
        $clean['focus_keyword']          = isset( $row['focus_keyword'] ) ? sanitize_text_field( $row['focus_keyword'] ) : '';

        $clean['buy_link']               = isset( $row['buy_link'] ) ? esc_url_raw( $row['buy_link'] ) : '';

        // Optional status column; fallback = draft (Hocam B seçeneği)
        $status  = isset( $row['status'] ) ? sanitize_text_field( $row['status'] ) : 'draft';
        $status  = strtolower( $status );
        $allowed = [ 'publish', 'draft', 'pending' ];
        if ( ! in_array( $status, $allowed, true ) ) {
            $status = 'draft';
        }
        $clean['status'] = $status;

        // Pass-through for helper fields set earlier in the pipeline
        $clean['_taxonomy_terms'] = $row['_taxonomy_terms'] ?? [];
        $clean['_image_id']       = $row['_image_id'] ?? 0;

        return $clean;
    }

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
        // CONTENT BUILDER (Block-based)
        // ---------------------------

        /**
         * NEW SYSTEM — Gutenberg Block-Based Content Builder
         * Creates 3 blocks:
         * 1) Paragraph block → product_description
         * 2) Shortcode block → audio_shorth_code
         * 3) Shortcode block → pdf_player_shorth_code
         */

        $blocks = '';

        // 1) Description Block
        if ( ! empty( $row['product_description'] ) ) {
            $desc    = wp_kses_post( $row['product_description'] );
            $blocks .= "<!-- wp:paragraph -->\n{$desc}\n<!-- /wp:paragraph -->\n\n";
        }

        // 2) Audio Player Shortcode Block
        if ( ! empty( $row['audio_shorth_code'] ) ) {
            $audio   = trim( $row['audio_shorth_code'] );
            $blocks .= "<!-- wp:shortcode -->\n{$audio}\n<!-- /wp:shortcode -->\n\n";
        }

        // 3) PDF Player Shortcode Block
        if ( ! empty( $row['pdf_player_shorth_code'] ) ) {
            $pdf     = trim( $row['pdf_player_shorth_code'] );
            $blocks .= "<!-- wp:shortcode -->\n{$pdf}\n<!-- /wp:shortcode -->\n\n";
        }

        return $blocks;
    }

    /**
     * Create or update a post from a sanitized CSV row.
     *
     * - Resolves CPT from cpt_taxonomy
     * - Uses group_id for duplicate detection (update vs create)
     * - Applies status with safe fallback to draft
     * - Builds Gutenberg block content (paragraph + shortcode blocks)
     * - Sets taxonomy terms, featured image and Rank Math meta
     *
     * @param array $row Raw CSV row
     * @return array{success:bool,post_id?:int,error?:string}
     */
    public function create_post_from_row( $row ) {

        $mapper    = new WR_CPT_Mapper();
        $post_type = $mapper->resolve_post_type( $row['cpt_taxonomy'] ?? '' );

        if ( ! $post_type ) {
            return [ 'error' => 'Unknown CPT type: ' . ( $row['cpt_taxonomy'] ?? '' ) ];
        }

        // Normalize & sanitize CSV fields
        $clean = $this->sanitize_row( $row );

        $group_id      = $clean['group_id'];
        $title         = $clean['product_title'];
        $status        = $clean['status'];
        $buy_link      = $clean['buy_link'];
        $seo_title     = $clean['seo_title'];
        $seo_desc      = $clean['seo_description'] ?: $clean['short_description'];
        $focus_keyword = $clean['focus_keyword'];

        // Block-based content (paragraph + shortcode blocks)
        $post_content = $this->build_content( $clean );

        // Slug: CSV slug preferred; fallback: title
        $slug_source = ! empty( $row['slug'] ) ? $row['slug'] : $title;
        $slug        = sanitize_title( $slug_source );

        // -------------------------------
        // 1) group_id based duplicate check
        // -------------------------------
        $post_id   = 0;
        $is_update = false;

        if ( ! empty( $group_id ) ) {
            $existing = get_posts( [
                'post_type'      => $post_type,
                'posts_per_page' => 1,
                'post_status'    => 'any',
                'meta_key'       => 'group_id',
                'meta_value'     => $group_id,
                'fields'         => 'ids',
            ] );

            if ( ! empty( $existing ) ) {
                $post_id   = (int) $existing[0];
                $is_update = true;
            }
        }

        // -------------------------------
        // 2) Insert or update post
        // -------------------------------
        $post_data = [
            'post_type'    => $post_type,
            'post_status'  => $status,
            'post_content' => $post_content,
            'post_name'    => $slug,
        ];

        if ( $is_update ) {
            // Eğer başlıkları elle düzenlemek istemezsen, şu satırı açabiliriz:
            // $post_data['post_title'] = $title;
            $post_data['ID'] = $post_id;
            $post_id         = wp_update_post( $post_data, true );
        } else {
            $post_data['post_title'] = $title;
            $post_id                 = wp_insert_post( $post_data, true );
        }

        if ( is_wp_error( $post_id ) ) {
            return [ 'error' => $post_id->get_error_message() ];
        }

        // -------------------------------
        // 3) Featured image (if any)
        // -------------------------------
        if ( ! empty( $clean['_image_id'] ) ) {
            set_post_thumbnail( $post_id, intval( $clean['_image_id'] ) );
        }

        // -------------------------------
        // 4) Taxonomies
        // -------------------------------
        if ( ! empty( $clean['_taxonomy_terms'] ) ) {
            wp_set_object_terms(
                $post_id,
                $clean['_taxonomy_terms'],
                $mapper->get_taxonomy_for_post_type( $post_type )
            );
        }

        // -------------------------------
        // 5) Meta fields (group_id, buy_link, Rank Math)
        // -------------------------------
        if ( ! empty( $group_id ) ) {
            update_post_meta( $post_id, 'group_id', $group_id );
        }

        if ( ! empty( $buy_link ) ) {
            update_post_meta( $post_id, 'buy_link', $buy_link );
        }

        // RankMath meta
        if ( ! empty( $seo_title ) ) {
            update_post_meta( $post_id, 'rank_math_title', $seo_title );
        }

        if ( ! empty( $seo_desc ) ) {
            update_post_meta( $post_id, 'rank_math_description', $seo_desc );
        }

        if ( ! empty( $focus_keyword ) ) {
            update_post_meta( $post_id, 'rank_math_focus_keyword', $focus_keyword );
        }

        return [
            'success' => true,
            'post_id' => $post_id,
        ];
    }
}
