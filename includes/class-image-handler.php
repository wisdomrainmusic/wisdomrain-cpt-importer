<?php

if (!defined('ABSPATH')) exit;

class WR_CPT_Image_Handler {

    /**
     * Download a remote image and add it to media library
     * Returns attachment ID or false
     */
    public function download_and_attach($image_url, $post_id = 0) {

        if (empty($image_url)) {
            return false;
        }

        // Download file to temp
        $tmp = download_url($image_url);

        if (is_wp_error($tmp)) {
            return false;
        }

        // File name
        $filename = basename(parse_url($image_url, PHP_URL_PATH));

        // File type
        $filetype = wp_check_filetype($filename, null);

        // Build file array for wp_handle_sideload
        $file = [
            'name'     => $filename,
            'type'     => $filetype['type'],
            'tmp_name' => $tmp,
            'error'    => 0,
            'size'     => filesize($tmp),
        ];

        // Upload to WP
        $upload = wp_handle_sideload($file, [
            'test_form' => false,
        ]);

        if (!empty($upload['error'])) {
            @unlink($tmp);
            return false;
        }

        // Create attachment
        $attachment = [
            'post_mime_type' => $upload['type'],
            'post_title'     => sanitize_file_name($filename),
            'post_content'   => '',
            'post_status'    => 'inherit'
        ];

        $attach_id = wp_insert_attachment($attachment, $upload['file'], $post_id);

        if (is_wp_error($attach_id)) {
            return false;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);

        return $attach_id;
    }
}
