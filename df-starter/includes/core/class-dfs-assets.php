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
            self::asset_version('assets/css/admin.css')
        );
    }

    public function enqueue_frontend(): void
    {
        wp_enqueue_style(
            'dfs-front',
            DFS_URL . 'assets/css/front.css',
            [],
            self::asset_version('assets/css/front.css')
        );
    }

    /**
     * Version basée sur la date de modification du fichier pour casser le cache
     * navigateur à chaque changement, avec repli sur DFS_VERSION.
     */
    public static function asset_version(string $relative_path): string
    {
        $file = DFS_PATH . $relative_path;
        return file_exists($file) ? (string) filemtime($file) : DFS_VERSION;
    }
}
