<?php

if (!defined('ABSPATH')) {
    exit;
}

class DFS_Assets
{
    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend']);
    }

    public function enqueue_admin(): void
    {
        wp_enqueue_style(
            'dfs-admin',
            DFS_URL . 'assets/css/admin.css',
            [],
            DFS_VERSION
        );
    }

    public function enqueue_frontend(): void
    {
        wp_enqueue_style(
            'dfs-front',
            DFS_URL . 'assets/css/front.css',
            [],
            DFS_VERSION
        );
    }
}
