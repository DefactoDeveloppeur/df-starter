<?php

if (!defined('ABSPATH')) {
    exit;
}

class DFS_Capabilities_Manager
{
    const PAGE_SLUG    = 'df-capabilities';
    const NONCE_ACTION = 'dfs_save_caps';
    const NONCE_FIELD  = 'dfs_caps_nonce';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'handle_save']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function add_settings_page(): void
    {
        add_submenu_page(
            DFS_Settings_Page::MENU_SLUG,
            __('Accès Client DF', 'df-starter'),
            __('Accès Client DF', 'df-starter'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render']
        );
    }

    public function enqueue_assets($hook): void
    {
        if ($hook !== 'defacto-starter_page_' . self::PAGE_SLUG) {
            return;
        }

        wp_enqueue_script(
            'dfs-capabilities-manager',
            DFS_URL . 'assets/js/capabilities-manager.js',
            [],
            DFS_Assets::asset_version('assets/js/capabilities-manager.js'),
            true
        );
    }

    public function handle_save(): void
    {
        if (!isset($_POST[self::NONCE_FIELD])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission refusée.', 'df-starter'));
        }

        $nonce = sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD]));
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            wp_die(esc_html__('Nonce invalide ou expiré.', 'df-starter'));
        }

        $listed = (isset($_POST['dfs_menu_slugs']) && is_array($_POST['dfs_menu_slugs']))
            ? array_map('sanitize_text_field', wp_unslash($_POST['dfs_menu_slugs']))
            : [];

        $visible = (isset($_POST['dfs_visible_menus']) && is_array($_POST['dfs_visible_menus']))
            ? array_map('sanitize_text_field', wp_unslash($_POST['dfs_visible_menus']))
            : [];

        // Masqué = listé dans le formulaire mais non coché « visible ».
        $hidden = array_values(array_diff($listed, $visible));

        // Conserver les menus masqués absents du formulaire (plugin
        // temporairement inactif) pour ne pas perdre le réglage.
        $existing = (array) get_option(DFS_Roles::OPTION_HIDDEN_MENUS, []);
        foreach ($existing as $slug) {
            if (!in_array($slug, $listed, true) && !in_array($slug, $hidden, true)) {
                $hidden[] = $slug;
            }
        }

        update_option(DFS_Roles::OPTION_HIDDEN_MENUS, $hidden);

        // Application immédiate du miroir de caps sur le rôle.
        (new DFS_Roles())->sync_caps();

        wp_safe_redirect(add_query_arg(
            ['page' => self::PAGE_SLUG, 'updated' => '1'],
            admin_url('admin.php')
        ));
        exit;
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        require DFS_PATH . 'includes/views/capabilities-manager-page.php';
    }

    /**
     * Menus top-level proposables au masquage : le menu admin tel que vu par
     * l'administrateur courant, sans les séparateurs ni les menus toujours
     * masqués pour le client (extensions, réglages, starter…).
     *
     * @return array<string,string> [ slug de menu => libellé ]
     */
    public static function manageable_menus(): array
    {
        $items = [];

        foreach ((array) ($GLOBALS['menu'] ?? []) as $item) {
            $slug  = (string) ($item[2] ?? '');
            $title = (string) ($item[0] ?? '');

            if ($slug === '' || strpos($slug, 'separator') === 0) {
                continue;
            }
            if (in_array($slug, DFS_Admin_Cleanup::FORCED_HIDDEN_MENUS, true)) {
                continue;
            }

            // Retire les pastilles de compteur (« <span class="update-plugins">… »).
            $label = trim(wp_strip_all_tags(preg_replace('/<span.*$/s', '', $title)));
            if ($label === '') {
                $label = $slug;
            }

            $items[$slug] = $label;
        }

        return $items;
    }
}
