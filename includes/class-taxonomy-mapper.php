<?php

if (!defined('ABSPATH')) exit;

class WR_CPT_Mapper {

    /**
     * Map CSV cpt_taxonomy → real WordPress post types
     */
    private $post_type_map = [
        'Library'         => 'library',
        'Music'           => 'music',
        'Meditation'      => 'meditation',
        'Children Story'  => 'children_story',
        'Sleep Story'     => 'sleep_story',
        'Magazine'        => 'magazine',
    ];

    /**
     * Return WP post_type based on CSV cpt_taxonomy
     */
    public function resolve_post_type($csv_value) {

        $csv_value = trim($csv_value);

        if (isset($this->post_type_map[$csv_value])) {
            return $this->post_type_map[$csv_value];
        }

        return false; // not found — will trigger error
    }
}
