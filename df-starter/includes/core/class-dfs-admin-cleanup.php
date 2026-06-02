<?php

if (!defined('ABSPATH')) {
    exit;
}

class DFS_Admin_Cleanup
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'remove_menus_for_client_df'], 999);
        add_action('current_screen', [$this, 'restrict_admin_access']);
        add_action('admin_init', [$this, 'hide_updates']);
        add_action('admin_bar_menu', [$this, 'customize_admin_bar'], 999);
        add_filter('use_block_editor_for_post', '__return_false', 10);
        add_filter('manage_posts_columns', [$this, 'remove_posts_columns'], 10, 2);
    }

    private function should_restrict(): bool
    {
        return current_user_can(DFS_Roles::ROLE_SLUG) && !is_super_admin();
    }

    public function remove_menus_for_client_df(): void
    {
        if (!$this->should_restrict()) {
            return;
        }

        remove_menu_page('tools.php');
        remove_menu_page('edit-comments.php');
        remove_menu_page('plugins.php');
        remove_menu_page('themes.php');
        remove_menu_page('users.php');
        remove_menu_page('options-general.php');
        remove_submenu_page('index.php', 'update-core.php');
    }

    public function restrict_admin_access(): void
    {
        if (!$this->should_restrict()) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen) {
            return;
        }

        $allowed = ['edit', 'upload', 'media', 'edit.php?post_type=page', 'profile'];

        $is_allowed = in_array($screen->base, $allowed, true)
            || $screen->post_type === 'page'
            || $screen->post_type === 'post'
            || post_type_supports($screen->post_type, 'editor');

        if (!$is_allowed) {
            wp_safe_redirect(admin_url('profile.php'));
            exit;
        }
    }

    public function hide_updates(): void
    {
        if (!$this->should_restrict()) {
            return;
        }

        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');
        remove_action('admin_notices', 'update_nag', 3);
        add_filter('pre_site_transient_update_plugins', '__return_null');
        add_filter('pre_site_transient_update_themes', '__return_null');
        add_filter('pre_site_transient_update_core', '__return_null');
    }

    public function customize_admin_bar($wp_admin_bar): void
    {
        if (!$this->should_restrict()) {
            return;
        }

        $wp_admin_bar->remove_node('updates');
        $wp_admin_bar->remove_node('wp-logo');
        $wp_admin_bar->remove_node('comments');
    }

    public function remove_posts_columns($columns, $post_type)
    {
        unset($columns['comments']);
        return $columns;
    }
}
