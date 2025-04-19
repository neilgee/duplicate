<?php

/*
Plugin Name: Duper
Plugin URI: https://wpbeaches.com/duper
Description: Duplicate any post or page as a draft, including custom post types.
Author: Neil Gowran
Author URI: https://wpbeaches.com
Version: 1.0.0
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
Text Domain: duper
Domain Path: /languages
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function duper_duplicate_post_as_draft() {
    global $wpdb;

    if (! (isset($_GET['post']) || isset($_POST['post']) || (isset($_REQUEST['action']) && 'duper_duplicate_post_as_draft' === $_REQUEST['action']))) {
        wp_die(__('No post to duplicate has been supplied!', 'duper'));
    }

    $post_id = isset($_GET['post']) ? absint($_GET['post']) : absint($_POST['post']);
    $post = get_post($post_id);

    if (isset($post) && $post != null) {
        $new_post = array(
            'post_title'     => $post->post_title . ' ' . __('(Copy)', 'duper'),
            'post_content'   => $post->post_content,
            'post_status'    => 'draft',
            'post_type'      => $post->post_type,
            'post_author'    => get_current_user_id(),
            'post_excerpt'   => $post->post_excerpt,
            'post_parent'    => $post->post_parent,
            'post_password'  => $post->post_password,
            'post_name'      => '',
        );

        $new_post_id = wp_insert_post($new_post);

        $taxonomies = get_object_taxonomies($post->post_type);
        foreach ($taxonomies as $taxonomy) {
            $post_terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'slugs'));
            wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
        }

        $post_meta = $wpdb->get_results($wpdb->prepare("SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d", $post_id));
        foreach ($post_meta as $meta_info) {
            if ($meta_info->meta_key === '_wp_old_slug') continue;
            add_post_meta($new_post_id, $meta_info->meta_key, maybe_unserialize($meta_info->meta_value));
        }

        wp_redirect(admin_url('post.php?action=edit&post=' . $new_post_id));
        exit;
    } else {
        wp_die(__('Post creation failed, could not find original post.', 'duper'));
    }
}
add_action('admin_action_duper_duplicate_post_as_draft', 'duper_duplicate_post_as_draft');

function duper_duplicate_post_link($actions, $post) {
    if (current_user_can('edit_posts')) {
        $actions['duplicate'] = '<a href="' . wp_nonce_url('admin.php?action=duper_duplicate_post_as_draft&post=' . $post->ID, basename(__FILE__), 'duplicate_nonce') . '" title="' . esc_attr(__('Duplicate this item', 'duper')) . '" rel="permalink">' . __('Duplicate', 'duper') . '</a>';
    }
    return $actions;
}
add_filter('post_row_actions', 'duper_duplicate_post_link', 10, 2);
add_filter('page_row_actions', 'duper_duplicate_post_link', 10, 2);
