<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WR_CPT_Import_Runner {
    public function parse_csv( $file_path ) {

        if ( ! file_exists( $file_path ) ) {
            return [ 'error' => 'CSV file not found.' ];
        }

        $rows   = [];
        $header = [];

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

                    $rows[] = array_combine( $header, $data );
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
