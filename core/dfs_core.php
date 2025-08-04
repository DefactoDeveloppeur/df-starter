<?php

class DFS_core {

    const CPT_OPTION_KEY = 'df_show_projets';

    public function __construct() {
        include_once(MY_PLUGIN_PATH."/core/capabilities/class-df_capabilities.php");
        register_activation_hook(__FILE__, [$this, 'add_client_df_role']);
        $this->add_dynamic_roles();
        add_action('admin_menu', [$this, 'remove_menus_for_client_df'], 999);
        add_action('current_screen', [$this, 'restrict_admin_access']);
        add_action('admin_enqueue_scripts', [$this, 'init_enqueue_scripts']);
        add_action('wp_enqueue_scripts', [$this,'frontend_css_enqueue'] );
        add_action('admin_init', [$this, 'disable_comments']);
        add_action('admin_init', [$this, 'hide_updates']);
        add_action('admin_bar_menu', [$this, 'customize_admin_bar'], 999);
        add_filter('use_block_editor_for_post', '__return_false', 10);
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'conditionally_remove_projets_cpt'], 999);
        add_filter('manage_posts_columns', [$this, 'remove_posts_columns'], 10, 2 );
        add_filter('pre_set_site_transient_update_plugins', [$this, 'df_check_for_updates']);
        add_filter('upload_mimes', [$this, 'df_svg_mime_type'] );
        new Df_capabilities();
    }

    public function add_client_df_role() {

        if(wp_roles()->is_role( 'client_df' ))
            remove_role('client_df');

        add_role('client_df', 'Client DF', [
            'read' => true,
            'edit_posts' => true,
            'edit_others_posts' => true,
            'publish_posts' => true,
            'edit_pages' => true,
            'edit_others_pages' => true,
            'publish_pages' => true,
            'manage_categories' => true,
            'upload_files' => true,
            'read_private_pages' => true,
            'read_private_posts' => true,
            'edit_own_profile' => true,
            'edit_published_posts' => true,
            'edit_published_pages' => true,
            //'read_custom_post_type' => true, // générique, on affinera via post types
        ]);
    }

    private function add_dynamic_roles() {
        $role = get_role('client_df');

        $allcapabilities = array_keys(get_role('administrator')->capabilities);

        if(is_plugin_active("ameliabooking/ameliabooking.php")){
            foreach($allcapabilities as $cap) {
                if(str_contains($cap, 'amelia')) {
                    $role->add_cap($cap, true);
                }
            }
        }
    }

    public function init_enqueue_scripts() {
        wp_register_style( 'df_starter_wp_admin_css', plugins_url( 'df-starter/assets/admin_style.css' ), false, '1.0.0' );
        wp_enqueue_style( 'df_starter_wp_admin_css' );
    }
    public function frontend_css_enqueue(){
        wp_register_style( 'df_starter_wp_front_css', plugins_url( 'df-starter/assets/front_style.css' ), false, '1.0.0' );
        wp_enqueue_style( 'df_starter_wp_front_css' );
    }
    public function remove_menus_for_client_df() {
        if (!current_user_can('client_df') || is_admin() || is_super_admin()) return;

        remove_menu_page('tools.php');
        remove_menu_page('edit-comments.php');
        remove_menu_page('plugins.php');
        remove_menu_page('themes.php');
        remove_menu_page('users.php');
        remove_menu_page('options-general.php');
        remove_submenu_page('index.php', 'update-core.php');

    }

    public function restrict_admin_access() {
        if (!current_user_can('client_df') || is_admin() || is_super_admin()) return;

        $screen = get_current_screen();
        $allowed = ['edit', 'upload', 'edit.php?post_type=page', 'profile'];

        if (!in_array($screen->base, $allowed) && $screen->post_type !== 'page' && $screen->post_type !== 'post' && !post_type_supports($screen->post_type, 'editor')) {
            wp_redirect(admin_url('profile.php'));
            exit;
        }
    }

    public function disable_comments() {
        remove_menu_page('edit-comments.php');
        remove_submenu_page('options-general.php', 'options-discussion.php');

        add_filter('comments_open', '__return_false', 20, 2);
        add_filter('pings_open', '__return_false', 20, 2);
        add_filter('comments_array', '__return_empty_array', 10, 2);
    }

    public function hide_updates() {
        if (!current_user_can('client_df') || is_admin() || is_super_admin()) return;

        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');
        remove_action('admin_notices', 'update_nag', 3);
        add_filter('pre_site_transient_update_plugins', '__return_null');
        add_filter('pre_site_transient_update_themes', '__return_null');
        add_filter('pre_site_transient_update_core', '__return_null');
    }

    public function customize_admin_bar($wp_admin_bar) {
        if (!current_user_can('client_df') || is_admin() || is_super_admin()) return;

        $wp_admin_bar->remove_node('updates');
        $wp_admin_bar->remove_node('wp-logo');
        $wp_admin_bar->remove_node('comments');

    }

    public function add_settings_page() {
        $menu_slug = 'df-settings';

        add_menu_page(
            'Defacto Starter - Configuration',
            'Defacto Starter',
            'manage_options',
            $menu_slug,
            false,
            plugins_url( 'df-starter/assets/logo-menu.png' ),
            3
        );
        add_submenu_page(
            $menu_slug,
            'Defacto Starter - Configuration',
            'Configuration',
            'manage_options',
            $menu_slug,
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting('df_settings_group', self::CPT_OPTION_KEY);
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <style>

            </style>
            <div class="df_wp-admin-container">
                <!-- Header -->
                <div class="df_plugin-header">
                    <h2>
                        Defacto Starter - Paramètres
                    </h2>
                    <div class="subtitle">Options du site</div>
                </div>

                <form method="post" action="options.php">
                    <?php settings_fields('df_settings_group'); ?>
                    <?php do_settings_sections('df_settings_group'); ?>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Afficher le custom post type "Projets" installé par Divi</th>
                            <td>
                                <input type="checkbox" name="<?php echo esc_attr(self::CPT_OPTION_KEY); ?>" value="1" <?php checked(1, get_option(self::CPT_OPTION_KEY), true); ?> />
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(); ?>

                </form>


            </div>

            <!-- Notification -->
            <div class="df_notification" id="notification"></div>

        </div>
        <?php
    }

    public function conditionally_remove_projets_cpt() {
        if ( get_option(self::CPT_OPTION_KEY)  == "" ) {
            remove_menu_page('edit.php?post_type=project');
        }
    }

    public function remove_posts_columns( $columns, $post_type ){
        unset( $columns['comments'] );
        return $columns;
    }

    function df_check_for_updates($transient) {
        if (empty($transient->checked)) return $transient;

        $plugin_slug = 'df-starter';
        $plugin_file = 'df-starter.php';
        $remote_url = 'https://www.studiodefacto.com/starter_plugin/update.json'; // Ton URL JSON

        $remote = wp_remote_get($remote_url);
        if (is_wp_error($remote) || wp_remote_retrieve_response_code($remote) !== 200) return $transient;

        $remote_data = json_decode(wp_remote_retrieve_body($remote));
        if (version_compare($remote_data->version, $transient->checked[$plugin_slug . '/' . $plugin_file], '>')) {
            $transient->response[$plugin_slug . '/' . $plugin_file] = (object)[
                'slug' => $remote_data->slug,
                'plugin' => $remote_data->slug . '/' . $plugin_file,
                'new_version' => $remote_data->version,
                'url' => $remote_data->homepage,
                'package' => $remote_data->download_url
            ];
        }

        return $transient;
    }

    function df_svg_mime_type( $mimes = array() ) {
        $mimes['svg']  = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';
        return $mimes;
    }


}