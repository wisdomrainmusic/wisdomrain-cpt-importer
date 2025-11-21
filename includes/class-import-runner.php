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

        $mapper = new WR_CPT_Mapper();

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
}
