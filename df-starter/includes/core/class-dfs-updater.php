<?php

if (!defined('ABSPATH')) {
    exit;
}

class DFS_Updater
{
    const REMOTE_URL = 'https://www.studiodefacto.com/starter_plugin/update.json';
    const PLUGIN_SLUG = 'df-starter';
    const PLUGIN_FILE = 'df-starter.php';

    public function register(): void
    {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_updates']);
    }

    public function check_for_updates($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        $plugin_path = self::PLUGIN_SLUG . '/' . self::PLUGIN_FILE;
        if (!isset($transient->checked[$plugin_path])) {
            return $transient;
        }

        $remote = wp_remote_get(self::REMOTE_URL, ['timeout' => 10]);
        if (is_wp_error($remote) || wp_remote_retrieve_response_code($remote) !== 200) {
            return $transient;
        }

        $remote_data = json_decode(wp_remote_retrieve_body($remote));
        if (!$remote_data || empty($remote_data->version)) {
            return $transient;
        }

        if (version_compare($remote_data->version, $transient->checked[$plugin_path], '>')) {
            $transient->response[$plugin_path] = (object) [
                'slug'        => $remote_data->slug,
                'plugin'      => $remote_data->slug . '/' . self::PLUGIN_FILE,
                'new_version' => $remote_data->version,
                'url'         => $remote_data->homepage ?? '',
                'package'     => $remote_data->download_url ?? '',
                // Icône affichée dans la liste des mises à jour (sinon : pièce de puzzle par défaut).
                'icons'       => isset($remote_data->icons) ? (array) $remote_data->icons : [],
            ];
        }

        return $transient;
    }
}
