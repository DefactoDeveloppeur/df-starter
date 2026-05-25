<?php

if (!defined('ABSPATH')) {
    exit;
}

class DFS_Comment_Blocker
{
    public function register(): void
    {
        add_filter('comments_open', '__return_false', 9999);
        add_filter('pings_open', '__return_false', 9999);
        add_filter('comments_array', '__return_empty_array', 9999);
        add_filter('rest_endpoints', [$this, 'remove_rest_comments_endpoint'], 9999);
        add_action('init', [$this, 'block_wp_comments_post_early'], 1);
        add_filter('xmlrpc_methods', [$this, 'filter_xmlrpc_methods'], 9999);
        add_action('template_redirect', [$this, 'disable_comments_feed'], 0);
        add_action('admin_menu', [$this, 'remove_comments_admin_menu']);
        add_filter('dashboard_glance_items', [$this, 'remove_at_a_glance_comments'], 9999);
        add_action('wp_before_admin_bar_render', [$this, 'remove_admin_bar_comments']);
        add_action('admin_init', [$this, 'hide_discussion_settings_ui']);
    }

    public function remove_rest_comments_endpoint(array $endpoints): array
    {
        unset($endpoints['/wp/v2/comments']);
        return $endpoints;
    }

    public function block_wp_comments_post_early(): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if ($path && basename($path) === 'wp-comments-post.php') {
            status_header(403);
            nocache_headers();
            exit;
        }

        add_filter('preprocess_comment', function () {
            wp_die(esc_html__('Les commentaires sont fermés.', 'df-starter'), 403);
        }, 0);
    }

    public function filter_xmlrpc_methods(array $methods): array
    {
        unset(
            $methods['pingback.ping'],
            $methods['wp.newComment'],
            $methods['wp.editComment'],
            $methods['wp.deleteComment'],
            $methods['wp.getComment'],
            $methods['wp.getComments']
        );
        return $methods;
    }

    public function disable_comments_feed(): void
    {
        if (is_comment_feed()) {
            status_header(410);
            header('Content-Type: text/plain; charset=' . get_bloginfo('charset'));
            echo 'Flux de commentaires désactivé.';
            exit;
        }
    }

    public function remove_comments_admin_menu(): void
    {
        remove_menu_page('edit-comments.php');
        remove_submenu_page('options-general.php', 'options-discussion.php');
    }

    public function remove_at_a_glance_comments($items)
    {
        if (is_array($items)) {
            return array_filter($items, function ($item) {
                return stripos(wp_strip_all_tags($item), 'comment') === false;
            });
        }
        return $items;
    }

    public function remove_admin_bar_comments(): void
    {
        global $wp_admin_bar;
        if ($wp_admin_bar) {
            $wp_admin_bar->remove_menu('comments');
        }
    }

    public function hide_discussion_settings_ui(): void
    {
        add_filter('option_default_comment_status', '__return_false', 9999);
        add_filter('option_default_ping_status', '__return_false', 9999);
    }
}
