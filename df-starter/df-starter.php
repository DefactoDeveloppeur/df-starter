<?php
/**
 * Plugin Name: Defacto - Starter Pack
 * Description: Mise en place des configurations minimum pour les sites créés par DEFACTO.
 * Version: 2.2.7.7
 * Author: DEFACTO
 * Author URI: https://www.studiodefacto.com
 * Text Domain: df-starter
 */

if (!defined('ABSPATH')) {
    exit;
}

define('DFS_VERSION', '2.2.7.7');
define('DFS_FILE', __FILE__);
define('DFS_PATH', plugin_dir_path(__FILE__));
define('DFS_URL', plugin_dir_url(__FILE__));
define('DFS_BASENAME', plugin_basename(__FILE__));

require_once DFS_PATH . 'includes/class-dfs-plugin.php';

DFS_Plugin::instance()->run();

register_activation_hook(__FILE__, ['DFS_Roles', 'add_client_df_role']);
