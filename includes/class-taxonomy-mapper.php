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
     * Map CPTs to their taxonomy (auto-create parent & subcategory for each CPT)
     */
    private $taxonomy_map = [
        'library'        => 'library_category',
        'music'          => 'music_category',
        'meditation'     => 'meditation_category',
        'children_story' => 'children_category',
        'sleep_story'    => 'sleep_category',
        'magazine'       => 'magazine_category',
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

    public function get_taxonomy_for_post_type($post_type) {
        return isset($this->taxonomy_map[$post_type])
            ? $this->taxonomy_map[$post_type]
            : false;
    }

    public function get_or_create_parent_term($taxonomy, $term_name) {

        if (empty($term_name)) {
            return false;
        }

        $term = term_exists($term_name, $taxonomy);

        if ($term && isset($term['term_id'])) {
            return $term['term_id'];
        }

        $new = wp_insert_term($term_name, $taxonomy);

        if (is_wp_error($new)) {
            return false;
        }

        return $new['term_id'];
    }

    public function get_or_create_child_term($taxonomy, $parent_id, $term_name) {

        if (empty($term_name)) {
            return false;
        }

        $term = term_exists($term_name, $taxonomy);

        if ($term && isset($term['term_id'])) {
            return $term['term_id'];
        }

        $new = wp_insert_term($term_name, $taxonomy, [
            'parent' => $parent_id
        ]);

        if (is_wp_error($new)) {
            return false;
        }

        return $new['term_id'];
    }
}
