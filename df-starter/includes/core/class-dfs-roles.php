<?php

if (!defined('ABSPATH')) {
    exit;
}

class DFS_Roles
{
    const ROLE_SLUG = 'client_df';

    /**
     * Menus d'admin masqués pour le rôle Client DF (slugs de menus top-level,
     * ex. « df-apimmo » ou « edit.php?post_type=produit »). Alimenté par la
     * page « Accès Client DF ».
     */
    const OPTION_HIDDEN_MENUS = 'dfs_client_df_hidden_menus';

    public function register(): void
    {
        add_action('init', [$this, 'sync_caps']);
    }

    /**
     * Slugs des menus masqués pour le client. Filtrable via
     * `dfs_client_df_hidden_menus` (par ex. depuis le functions.php du thème).
     *
     * @return string[]
     */
    public static function hidden_menus(): array
    {
        $menus = (array) get_option(self::OPTION_HIDDEN_MENUS, []);

        /**
         * Filtre la liste des menus d'admin masqués pour le rôle Client DF
         * (en complément de ceux décochés en admin).
         *
         * @param string[] $menus Slugs de menus top-level.
         */
        $menus = (array) apply_filters('dfs_client_df_hidden_menus', $menus);

        return array_values(array_unique(array_map('strval', $menus)));
    }

    /**
     * Capacités JAMAIS accordées au rôle Client DF. C'est la vraie barrière de
     * sécurité du modèle « miroir admin » : masquer un menu est cosmétique (un
     * client pourrait passer par une URL directe ou l'API REST), alors qu'une
     * cap absente bloque partout. On refuse tout ce qui permettrait de
     * s'auto-promouvoir (créer un compte admin), d'installer ou d'éditer du
     * code, ou de casser le site.
     *
     * @return string[]
     */
    public static function denied_caps(): array
    {
        return [
            // Utilisateurs : create/promote permettrait de se créer un compte
            // administrateur (y compris via l'API REST).
            'create_users', 'delete_users', 'edit_users', 'list_users',
            'promote_users', 'remove_users', 'add_users',
            // Extensions / thèmes / édition de code.
            'install_plugins', 'activate_plugins', 'deactivate_plugins',
            'edit_plugins', 'delete_plugins', 'update_plugins', 'resume_plugins',
            'install_themes', 'switch_themes', 'edit_themes', 'delete_themes',
            'update_themes', 'resume_themes', 'edit_theme_options', 'edit_files',
            'edit_css', 'unfiltered_html', 'unfiltered_upload',
            // Coeur / outils sensibles.
            'update_core', 'install_languages', 'update_languages',
            'setup_network', 'delete_site', 'import',
            'view_site_health_checks', 'manage_privacy_options',
            'export_others_personal_data', 'erase_others_personal_data',
            // Multisite.
            'manage_network', 'manage_sites', 'manage_network_users',
            'manage_network_themes', 'manage_network_options',
            'manage_network_plugins', 'upgrade_network',
        ];
    }

    /**
     * Aligne le rôle Client DF sur l'administrateur : toutes les caps de
     * l'admin (dont celles des plugins : WooCommerce, Rank Math, etc.) moins
     * la liste noire. Les nouveaux plugins sont donc utilisables par le client
     * sans configuration ; la restriction se fait par masquage de menus +
     * blocage d'écrans (DFS_Admin_Cleanup).
     */
    public function sync_caps(): void
    {
        $role  = get_role(self::ROLE_SLUG);
        $admin = get_role('administrator');
        if (!$role || !$admin) {
            return;
        }

        $denied = self::denied_caps();

        foreach (array_keys(array_filter($admin->capabilities)) as $cap) {
            if (preg_match('/^level_\d+$/', $cap)) {
                continue;
            }

            $should = !in_array($cap, $denied, true);
            $has    = !empty($role->capabilities[$cap]);

            if ($should && !$has) {
                $role->add_cap($cap, true);
            } elseif (!$should && $has) {
                $role->remove_cap($cap);
            }
        }

        // Retire aussi les caps refusées héritées d'anciennes versions du
        // plugin, même si l'admin ne les possède pas (ou plus).
        foreach ($denied as $cap) {
            if (!empty($role->capabilities[$cap])) {
                $role->remove_cap($cap);
            }
        }
    }

    public static function add_client_df_role(): void
    {
        if (wp_roles()->is_role(self::ROLE_SLUG)) {
            remove_role(self::ROLE_SLUG);
        }

        // Caps de départ : le miroir complet est appliqué par sync_caps() dès
        // le prochain chargement (hook init).
        add_role(self::ROLE_SLUG, 'Client DF', self::base_caps());
    }

    /**
     * Capacités minimales du rôle à sa création (édition de contenu). Le reste
     * est synchronisé dynamiquement depuis le rôle administrateur.
     */
    public static function base_caps(): array
    {
        return [
            'read'                  => true,
            'edit_posts'            => true,
            'edit_others_posts'     => true,
            'publish_posts'         => true,
            'edit_pages'            => true,
            'edit_others_pages'     => true,
            'publish_pages'         => true,
            'manage_categories'     => true,
            'upload_files'          => true,
            'read_private_pages'    => true,
            'read_private_posts'    => true,
            'edit_own_profile'      => true,
            'edit_published_posts'  => true,
            'edit_published_pages'  => true,
        ];
    }
}
