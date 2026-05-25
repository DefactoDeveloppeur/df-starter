<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('is_plugin_active')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
?>
<div class="wrap">
    <div class="df_plugin-header">
        <h2><?php esc_html_e('Plugins indispensables', 'df-starter'); ?></h2>
        <div class="subtitle"><?php esc_html_e('Liste des plugins indispensables pour tout client', 'df-starter'); ?></div>
    </div>
    <div class="df_plugins-grid">
        <?php foreach (DFS_Plugin_Manager::recommended_plugins() as $slug => $plugin) {
            DFS_Plugin_Manager::render_card($slug, $plugin);
        } ?>
    </div>

    <div class="df_plugin-header">
        <h2><?php esc_html_e('Plugins optionnels', 'df-starter'); ?></h2>
        <div class="subtitle"><?php esc_html_e('Liste des plugins optionnels pour tout client', 'df-starter'); ?></div>
    </div>
    <div class="df_plugins-grid">
        <?php foreach (DFS_Plugin_Manager::optional_plugins() as $slug => $plugin) {
            DFS_Plugin_Manager::render_card($slug, $plugin);
        } ?>
    </div>
</div>
