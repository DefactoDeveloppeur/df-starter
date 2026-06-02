<?php

if (!defined('ABSPATH')) {
    exit;
}

class DFS_Roles
{
    const ROLE_SLUG = 'client_df';

    /**
     * Source de vérité des capacités « plugin » accordées au rôle Client DF.
     * Tableau associatif : [ capacité => true ].
     */
    const OPTION_CAPS = 'dfs_client_df_caps';

    public function register(): void
    {
        add_action('init', [$this, 'sync_dynamic_caps']);
        add_filter('map_meta_cap', [$this, 'allow_attachment_management'], 10, 4);
    }

    /**
     * Autorise le rôle Client DF à modifier et supprimer les médias sans lui
     * accorder les caps de suppression d'articles/pages. Les attachments
     * partagent le capability_type « post » : on intercepte donc les meta caps
     * edit_post / delete_post / read_post pour les seuls attachments et on les
     * ramène à « upload_files », que le rôle possède déjà.
     *
     * @param string[] $caps    Caps primitives résolues par WordPress.
     * @param string   $cap     Meta cap demandée.
     * @param int      $user_id Utilisateur testé.
     * @param array    $args    $args[0] = ID de l'objet visé.
     * @return string[]
     */
    public function allow_attachment_management(array $caps, string $cap, int $user_id, array $args): array
    {
        if (!in_array($cap, ['edit_post', 'delete_post', 'read_post'], true)) {
            return $caps;
        }

        if (empty($args[0]) || !user_can($user_id, self::ROLE_SLUG)) {
            return $caps;
        }

        $post = get_post($args[0]);
        if ($post && $post->post_type === 'attachment') {
            return ['upload_files'];
        }

        return $caps;
    }

    public static function add_client_df_role(): void
    {
        if (wp_roles()->is_role(self::ROLE_SLUG)) {
            remove_role(self::ROLE_SLUG);
        }

        add_role(self::ROLE_SLUG, 'Client DF', self::base_caps());

        // Ne pré-coche que les caps historiquement accordées (Rank Math), sans
        // écraser un réglage déjà choisi par l'admin lors d'une réactivation.
        if (get_option(self::OPTION_CAPS) === false) {
            add_option(self::OPTION_CAPS, self::default_managed_caps());
        }
    }

    /**
     * Capacités WordPress « de base » toujours accordées au client (édition de
     * contenu). Elles ne sont jamais gérées ni retirées par la page d'admin.
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

    /**
     * Caps pré-accordées au premier déploiement pour conserver le comportement
     * historique (analyse SEO Rank Math). Tout autre cap est refusé par défaut.
     */
    public static function default_managed_caps(): array
    {
        return [
            'rank_math_onpage_analysis' => true,
            'rank_math_onpage_general'  => true,
            'rank_math_onpage_advanced' => true,
            'rank_math_onpage_snippet'  => true,
            'rank_math_onpage_social'   => true,
        ];
    }

    /**
     * Détecte les capacités apportées par les plugins actifs : on part de toutes
     * les caps de l'administrateur et on en retire les caps natives WordPress et
     * les caps de base du client.
     *
     * @return string[] Liste de capacités, triée.
     */
    public static function detect_plugin_caps(): array
    {
        $admin = get_role('administrator');
        if (!$admin) {
            return [];
        }

        $exclude = array_merge(self::core_caps(), array_keys(self::base_caps()));
        $caps    = [];

        foreach (array_keys($admin->capabilities) as $cap) {
            if (in_array($cap, $exclude, true)) {
                continue;
            }
            if (preg_match('/^level_\d+$/', $cap)) {
                continue;
            }
            $caps[] = $cap;
        }

        sort($caps);
        return $caps;
    }

    /**
     * Aligne le rôle Client DF sur l'option : ajoute les caps cochées, retire
     * celles décochées. Ne touche qu'aux caps « plugin » détectées.
     */
    public function sync_dynamic_caps(): void
    {
        $role = get_role(self::ROLE_SLUG);
        if (!$role) {
            return;
        }

        $granted = (array) get_option(self::OPTION_CAPS, []);

        foreach (self::detect_plugin_caps() as $cap) {
            $should = !empty($granted[$cap]);
            $has    = !empty($role->capabilities[$cap]);

            if ($should && !$has) {
                $role->add_cap($cap, true);
            } elseif (!$should && $has) {
                $role->remove_cap($cap);
            }
        }
    }

    /**
     * Capacités natives de WordPress (single + multisite). Sert de liste
     * d'exclusion pour isoler les caps apportées par les plugins.
     */
    public static function core_caps(): array
    {
        return [
            // Thèmes / plugins / coeur
            'switch_themes', 'edit_themes', 'edit_theme_options', 'install_themes',
            'update_themes', 'delete_themes', 'resume_themes', 'edit_css',
            'activate_plugins', 'edit_plugins', 'install_plugins', 'update_plugins',
            'delete_plugins', 'deactivate_plugins', 'resume_plugins', 'update_core',
            'edit_files', 'edit_dashboard', 'customize', 'install_languages',
            'update_languages', 'view_site_health_checks',
            // Utilisateurs
            'edit_users', 'create_users', 'delete_users', 'list_users',
            'remove_users', 'promote_users', 'add_users',
            // Options / divers
            'manage_options', 'moderate_comments', 'edit_comment',
            'manage_categories', 'manage_links', 'upload_files', 'import',
            'export', 'unfiltered_html', 'unfiltered_upload', 'delete_site',
            'manage_privacy_options', 'export_others_personal_data',
            'erase_others_personal_data',
            // Articles
            'edit_posts', 'edit_others_posts', 'edit_published_posts',
            'publish_posts', 'delete_posts', 'delete_others_posts',
            'delete_published_posts', 'delete_private_posts', 'edit_private_posts',
            'read_private_posts',
            // Pages
            'edit_pages', 'edit_others_pages', 'edit_published_pages',
            'publish_pages', 'delete_pages', 'delete_others_pages',
            'delete_published_pages', 'delete_private_pages', 'edit_private_pages',
            'read_private_pages',
            // Lecture / profil
            'read', 'edit_own_profile',
            // Multisite
            'manage_network', 'manage_sites', 'manage_network_users',
            'manage_network_themes', 'manage_network_options',
            'manage_network_plugins', 'upgrade_network', 'setup_network',
        ];
    }
}
