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
            DFS_VERSION,
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

        $manageable = DFS_Roles::detect_plugin_caps();
        $submitted  = (isset($_POST['dfs_caps']) && is_array($_POST['dfs_caps']))
            ? array_map('sanitize_text_field', wp_unslash($_POST['dfs_caps']))
            : [];

        $granted = [];

        // 1) Les caps détectées et cochées sont accordées.
        foreach ($manageable as $cap) {
            if (in_array($cap, $submitted, true)) {
                $granted[$cap] = true;
            }
        }

        // 2) On conserve les caps accordées dont le plugin est temporairement
        //    inactif (donc absentes du formulaire) pour ne pas perdre le réglage.
        $existing = (array) get_option(DFS_Roles::OPTION_CAPS, []);
        foreach ($existing as $cap => $value) {
            if ($value && !in_array($cap, $manageable, true)) {
                $granted[$cap] = true;
            }
        }

        update_option(DFS_Roles::OPTION_CAPS, $granted);

        // Application immédiate sur le rôle.
        (new DFS_Roles())->sync_dynamic_caps();

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
     * Regroupe les capacités par plugin. On ignore les verbes CRUD en tête de
     * cap pour retomber sur le « sujet » (ex : manage_woocommerce => woocommerce,
     * amelia_read_menu => amelia).
     *
     * @param string[] $caps
     * @return array<string,string[]> [ clé de groupe => caps triées ]
     */
    public static function group_caps(array $caps): array
    {
        $verbs = [
            'edit', 'read', 'delete', 'manage', 'view', 'publish', 'create',
            'update', 'install', 'uninstall', 'list', 'remove', 'promote',
            'activate', 'deactivate', 'switch', 'moderate', 'import', 'export',
            'add', 'assign', 'access',
        ];

        $groups = [];

        foreach ($caps as $cap) {
            $parts = explode('_', $cap);
            $key   = null;

            foreach ($parts as $part) {
                if (!in_array($part, $verbs, true)) {
                    $key = $part;
                    break;
                }
            }

            if ($key === null || $key === '') {
                $key = $parts[0] ?? 'autres';
            }

            $groups[$key][] = $cap;
        }

        ksort($groups);
        foreach ($groups as &$group) {
            sort($group);
        }
        unset($group);

        return $groups;
    }

    /**
     * Nom lisible d'un groupe de capacités.
     */
    public static function friendly_group(string $key): string
    {
        $map = [
            'amelia'       => 'Amelia',
            'rank'         => 'Rank Math SEO',
            'wpseo'        => 'Yoast SEO',
            'woocommerce'  => 'WooCommerce',
            'shop'         => 'WooCommerce',
            'wc'           => 'WooCommerce',
            'elementor'    => 'Elementor',
            'wpforms'      => 'WPForms',
            'gravityforms' => 'Gravity Forms',
            'gform'        => 'Gravity Forms',
            'acf'          => 'Advanced Custom Fields',
        ];

        return $map[$key] ?? ucwords(str_replace(['-', '_'], ' ', $key));
    }

    /**
     * Transforme une capacité en libellé lisible (sans le préfixe du groupe).
     */
    public static function cap_label(string $cap): string
    {
        return ucfirst(str_replace('_', ' ', $cap));
    }
}
