<?php

if (!defined('ABSPATH')) {
    exit;
}

class DFS_Settings_Page
{
    const MENU_SLUG = 'df-settings';
    const OPTION_GROUP = 'df_settings_group';
    const OPTION_SHOW_PROJETS = 'df_show_projets';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'add_menu'], 5);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'conditionally_remove_projets_cpt'], 999);
    }

    public function add_menu(): void
    {
        add_menu_page(
            __('Defacto Starter - Configuration', 'df-starter'),
            __('Defacto Starter', 'df-starter'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render'],
            DFS_URL . 'assets/logo-menu.png',
            3
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Defacto Starter - Configuration', 'df-starter'),
            __('Configuration', 'df-starter'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render']
        );
    }

    public function register_settings(): void
    {
        register_setting(self::OPTION_GROUP, self::OPTION_SHOW_PROJETS, [
            'type'              => 'boolean',
            'sanitize_callback' => function ($v) { return $v ? 1 : 0; },
            'default'           => 0,
        ]);
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        require DFS_PATH . 'includes/views/settings-page.php';
    }

    public function conditionally_remove_projets_cpt(): void
    {
        if (!get_option(self::OPTION_SHOW_PROJETS)) {
            remove_menu_page('edit.php?post_type=project');
        }
    }
}
