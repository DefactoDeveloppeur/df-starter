<?php
/**
 * Plugin Name: Defacto - Starter Pack
 * Description: Mise en place des configuration minimum pour les sites créés par DEFACTO.
 * Version: 1.1.7
 * Author: DEFACTO
 * Author URI: https://www.studiodefacto.com
 */


define('MY_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('MY_PLUGIN_URL', plugin_dir_url(__FILE__));

if (!defined('ABSPATH')) exit;

foreach (glob(__DIR__ . '/core/*.php') as $file) {
    require_once $file;
}

add_action('init', function() {
    new DFS_core();
    new DFS_Plugin_Manager();
    new DFS_CustomizePlugin();
    new DFS_ContactPlugin();
    //new DFS_ToDoListPlugin();
});

