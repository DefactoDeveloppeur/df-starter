<?php

if (!defined('ABSPATH')) {
    exit;
}

final class DFS_Plugin
{
    private static $instance = null;

    private $modules = [
        'core/class-dfs-roles.php'                 => 'DFS_Roles',
        'core/class-dfs-admin-cleanup.php'         => 'DFS_Admin_Cleanup',
        'core/class-dfs-comment-blocker.php'       => 'DFS_Comment_Blocker',
        'core/class-dfs-svg-support.php'           => 'DFS_Svg_Support',
        'core/class-dfs-updater.php'               => 'DFS_Updater',
        'core/class-dfs-capabilities-shortcode.php' => 'DFS_Capabilities_Shortcode',
        'core/class-dfs-google-reviews.php'        => 'DFS_Google_Reviews',
        'core/class-dfs-noindex-alert.php'         => 'DFS_Noindex_Alert',
        'core/class-dfs-assets.php'                => 'DFS_Assets',
        'admin/class-dfs-settings-page.php'        => 'DFS_Settings_Page',
        'admin/class-dfs-login-customizer.php'     => 'DFS_Login_Customizer',
        'admin/class-dfs-plugin-manager.php'       => 'DFS_Plugin_Manager',
        'admin/class-dfs-capabilities-manager.php' => 'DFS_Capabilities_Manager',
        'admin/class-dfs-contact-widget.php'       => 'DFS_Contact_Widget',
        'admin/class-dfs-media-filters.php'        => 'DFS_Media_Filters',
    ];

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function run(): void
    {
        foreach ($this->modules as $file => $class) {
            require_once DFS_PATH . 'includes/' . $file;
        }

        add_action('plugins_loaded', [$this, 'boot_modules']);
    }

    public function boot_modules(): void
    {
        foreach ($this->modules as $class) {
            if (class_exists($class)) {
                (new $class())->register();
            }
        }
    }
}
