<?php

if (!defined('ABSPATH')) {
    exit;
}

class DFS_Login_Customizer
{
    const PAGE_SLUG = 'df-customize-login-page';
    const NONCE_ACTION = 'dfs_login_customizer';

    const OPT_BG_COLOR = 'login_bg_color';
    const OPT_TEXT_COLOR = 'login_bg_text_color';
    const OPT_LOGO_URL = 'login_logo_url';

    const DEFAULT_BG = '#f0f0f1';
    const DEFAULT_TEXT = '#50575e';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('login_enqueue_scripts', [$this, 'enqueue_login_styles']);
        add_action('login_header', [$this, 'login_template_header']);
        add_action('login_footer', [$this, 'login_template_footer']);

        add_action('wp_ajax_dfs_update_login_color',         [$this, 'ajax_update_bg_color']);
        add_action('wp_ajax_dfs_update_login_text_color',    [$this, 'ajax_update_text_color']);
        add_action('wp_ajax_dfs_update_login_logo',          [$this, 'ajax_update_logo']);
    }

    public function add_settings_page(): void
    {
        add_submenu_page(
            DFS_Settings_Page::MENU_SLUG,
            __('Modifier page de connexion', 'df-starter'),
            __('Modifier page de connexion', 'df-starter'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render']
        );
    }

    public function enqueue_admin_assets($hook): void
    {
        if ($hook !== 'defacto-starter_page_' . self::PAGE_SLUG) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style(
            'dfs-login-customizer',
            DFS_URL . 'assets/css/login-customizer.css',
            [],
            DFS_VERSION
        );

        wp_enqueue_script(
            'dfs-login-customizer',
            DFS_URL . 'assets/js/login-customizer.js',
            ['jquery'],
            DFS_VERSION,
            true
        );

        wp_localize_script('dfs-login-customizer', 'dfsLoginCustomizer', [
            'ajaxUrl'    => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce(self::NONCE_ACTION),
            'defaultUrl' => admin_url('images/w-logo-blue.png?ver=20131202'),
            'defaultBg'  => self::DEFAULT_BG,
            'defaultText' => self::DEFAULT_TEXT,
        ]);
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        require DFS_PATH . 'includes/views/login-customizer-page.php';
    }

    private function verify_ajax(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission refusée'], 403);
        }
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], self::NONCE_ACTION)) {
            wp_send_json_error(['message' => 'Nonce invalide'], 403);
        }
    }

    public function ajax_update_bg_color(): void
    {
        $this->verify_ajax();

        $color = isset($_POST['color']) ? sanitize_hex_color(wp_unslash($_POST['color'])) : '';
        if (!$color) {
            wp_send_json_error(['message' => 'Couleur invalide']);
        }

        update_option(self::OPT_BG_COLOR, $color);
        wp_send_json_success(['message' => 'Background color updated successfully']);
    }

    public function ajax_update_text_color(): void
    {
        $this->verify_ajax();

        $color = isset($_POST['color']) ? sanitize_hex_color(wp_unslash($_POST['color'])) : '';
        if (!$color) {
            wp_send_json_error(['message' => 'Couleur invalide']);
        }

        update_option(self::OPT_TEXT_COLOR, $color);
        wp_send_json_success(['message' => 'Text color updated successfully']);
    }

    public function ajax_update_logo(): void
    {
        $this->verify_ajax();

        $url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
        if (!$url) {
            wp_send_json_error(['message' => 'URL invalide']);
        }

        update_option(self::OPT_LOGO_URL, $url);
        wp_send_json_success(['message' => 'Logo updated successfully']);
    }

    public function enqueue_login_styles(): void
    {
        $bg_color   = get_option(self::OPT_BG_COLOR, self::DEFAULT_BG);
        $logo_url   = get_option(self::OPT_LOGO_URL, admin_url('images/w-logo-blue.png?ver=20131202'));
        $text_color = get_option(self::OPT_TEXT_COLOR, self::DEFAULT_TEXT);

        wp_enqueue_style(
            'dfs-login',
            DFS_URL . 'assets/css/login.css',
            [],
            DFS_VERSION
        );

        $inline = sprintf(
            'body.login{background-color:%1$s !important;}'
            . '.login h1 a{background-image:url(%2$s) !important;background-size:contain !important;background-position:center !important;height:100px !important;width:80%% !important;}'
            . '#nav a,#backtoblog a{color:%3$s !important;}',
            esc_attr($bg_color),
            esc_url($logo_url),
            esc_attr($text_color)
        );
        wp_add_inline_style('dfs-login', $inline);
    }

    public function login_template_header(): void
    {
        $logo = esc_url(DFS_URL . 'assets/logo-principal.png');
        ?>
        <div class="login-page-container">
            <div class="left-part">
                <img src="<?php echo $logo; ?>" alt="">
            </div>
            <div class="right-part">
        <?php
    }

    public function login_template_footer(): void
    {
        ?>
            </div>
        </div>
        <?php
    }
}
