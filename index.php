<?php
/**
 * Plugin Name: Duper
 * Plugin URI: https://wpbeaches.com
 * Description: duplicate page/post
 * Author: <a href="https://wpbeaches.com">Neil Gowran</a>
 * Version: 1.0.0
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: duper
 * Domain Path: /languages
 *
 * @package Duper
 */

function bt_duplicate_post_as_draft() {
    global $wpdb;

    if (! (isset($_GET['post']) || isset($_POST['post']) || (isset($_REQUEST['action']) && 'bt_duplicate_post_as_draft' == $_REQUEST['action']))) {
        wp_die('No post to duplicate has been supplied!');
    }

    /*
     * Get the original post id
     */
    $post_id = (isset($_GET['post']) ? absint($_GET['post']) : absint($_POST['post']));
    $post = get_post($post_id);

    /*
     * Copy the post and insert it
     */
    if (isset($post) && $post != null) {
        $new_post = array(
            'post_title'     => $post->post_title . ' (Copy)',
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

        // Copy taxonomies
        $taxonomies = get_object_taxonomies($post->post_type);
        foreach ($taxonomies as $taxonomy) {
            $post_terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'slugs'));
            wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
        }

        // Copy meta
        $post_meta = $wpdb->get_results("SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id=$post_id");
        if (count($post_meta) != 0) {
            foreach ($post_meta as $meta_info) {
                if ($meta_info->meta_key === '_wp_old_slug') continue;
                add_post_meta($new_post_id, $meta_info->meta_key, maybe_unserialize($meta_info->meta_value));
            }
        }

        // Redirect to edit screen
        wp_redirect(admin_url('post.php?action=edit&post=' . $new_post_id));
        exit;
    } else {
        wp_die('Post creation failed, could not find original post: ' . $post_id);
    }
}
add_action('admin_action_bt_duplicate_post_as_draft', 'bt_duplicate_post_as_draft');

function bt_duplicate_post_link($actions, $post) {
    if (current_user_can('edit_posts')) {
        $actions['duplicate'] = '<a href="' . wp_nonce_url('admin.php?action=bt_duplicate_post_as_draft&post=' . $post->ID, basename(__FILE__), 'duplicate_nonce') . '" title="Duplicate this item" rel="permalink">Duplicate</a>';
    }
    return $actions;
}
add_filter('post_row_actions', 'bt_duplicate_post_link', 10, 2);
add_filter('page_row_actions', 'bt_duplicate_post_link', 10, 2);
