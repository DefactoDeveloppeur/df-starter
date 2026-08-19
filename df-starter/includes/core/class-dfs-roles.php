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

    /**
     * Liste des post types que le client peut gérer. Tableau de slugs,
     * alimenté par la page d'admin.
     */
    const OPTION_POST_TYPES = 'dfs_client_df_post_types';

    public function register(): void
    {
        add_action('init', [$this, 'sync_dynamic_caps']);
        // Priorité tardive : les CPT des plugins sont enregistrés sur init 10,
        // il faut qu'ils existent pour lire leurs capacités.
        add_action('init', [$this, 'sync_managed_post_type_caps'], 99);
        add_filter('map_meta_cap', [$this, 'remap_managed_post_type_caps'], 10, 4);
    }

    /**
     * Post types que le rôle Client DF doit pouvoir gérer comme des articles
     * natifs. Filtrable via le hook `dfs_client_df_post_types` (par ex. depuis
     * le functions.php du thème).
     *
     * Le post type « attachment » est géré à part (mappé sur upload_files).
     *
     * @return string[]
     */
    public static function managed_post_types(): array
    {
        $post_types = (array) get_option(self::OPTION_POST_TYPES, []);

        /**
         * Filtre la liste des post types gérés par le rôle Client DF (en
         * complément de ceux cochés en admin).
         *
         * @param string[] $post_types Slugs de post types.
         */
        $post_types = (array) apply_filters('dfs_client_df_post_types', $post_types);

        return array_values(array_unique(array_map('strval', $post_types)));
    }

    /**
     * Post types proposables à la sélection en admin : tous les CPT non natifs
     * dotés d'une interface d'administration. Deux mécanismes de déblocage
     * selon leur déclaration :
     *
     *  - capability_type « post » : remap des meta caps singulières vers les
     *    caps que le rôle possède déjà (cf. remap_managed_post_type_caps).
     *  - capability_type personnalisé (ex. « product ») : les caps dédiées du
     *    post type sont accordées au rôle (cf. sync_managed_post_type_caps).
     *
     * @return array<string,WP_Post_Type> [ slug => objet du post type ]
     */
    public static function detect_eligible_post_types(): array
    {
        $eligible = [];

        foreach (get_post_types(['_builtin' => false, 'show_ui' => true], 'objects') as $post_type) {
            if ($post_type->name === 'attachment') {
                continue;
            }

            $eligible[$post_type->name] = $post_type;
        }

        return $eligible;
    }

    /**
     * Caps dédiées d'un post type à capability_type personnalisé : toutes les
     * caps qu'il déclare, hors caps natives WordPress. Un CPT en
     * capability_type « post » renvoie une liste vide : lui est débloqué par le
     * remap des meta caps, sans toucher aux caps du rôle.
     *
     * Les meta caps singulières (edit_product, etc.) sont volontairement
     * incluses : pour un CPT déclaré sans `map_meta_cap => true`, WordPress
     * exige littéralement ces caps, et les accorder au rôle revient à un accès
     * « mini-admin » sur ce seul post type.
     *
     * @return string[]
     */
    private static function custom_caps_for(WP_Post_Type $post_type): array
    {
        // capability_type « post » (cap plurielle identique) : rien à accorder.
        if (($post_type->cap->edit_others_posts ?? '') === 'edit_others_posts') {
            return [];
        }

        $caps = [];

        foreach ((array) $post_type->cap as $cap) {
            if (!is_string($cap) || $cap === '' || in_array($cap, self::core_caps(), true)) {
                continue;
            }
            $caps[] = $cap;
        }

        return array_values(array_unique($caps));
    }

    /**
     * Aligne les caps du rôle sur la liste des post types cochés en admin :
     * accorde les caps dédiées des CPT sélectionnés, retire celles des CPT
     * décochés. Ne concerne que les CPT à capability_type personnalisé ; les
     * CPT en capability_type « post » passent par le remap des meta caps.
     */
    public function sync_managed_post_type_caps(): void
    {
        $role = get_role(self::ROLE_SLUG);
        if (!$role) {
            return;
        }

        $managed = self::managed_post_types();
        $grant   = [];
        $revoke  = [];

        foreach (get_post_types(['_builtin' => false], 'objects') as $post_type) {
            $caps = self::custom_caps_for($post_type);
            if (!$caps) {
                continue;
            }

            if (in_array($post_type->name, $managed, true)) {
                $grant = array_merge($grant, $caps);
            } else {
                $revoke = array_merge($revoke, $caps);
            }
        }

        // Un capability_type peut être partagé par plusieurs CPT (WooCommerce,
        // etc.) : ne retirer que les caps qu'aucun CPT coché n'utilise, et
        // jamais une cap accordée par ailleurs via la liste des capacités.
        $granted_caps = array_keys(array_filter((array) get_option(self::OPTION_CAPS, [])));
        $revoke       = array_diff($revoke, $grant, $granted_caps);

        foreach (array_unique($grant) as $cap) {
            if (empty($role->capabilities[$cap])) {
                $role->add_cap($cap, true);
            }
        }

        foreach ($revoke as $cap) {
            if (!empty($role->capabilities[$cap])) {
                $role->remove_cap($cap);
            }
        }
    }

    /**
     * Autorise le rôle Client DF à gérer certains contenus partageant le
     * capability_type « post » sans lui accorder les caps natives d'articles/
     * pages. On intercepte les meta caps singulières edit_post / delete_post /
     * read_post pour le seul rôle Client DF, et on les ramène à des caps que
     * le rôle possède déjà (même principe que pour les médias) :
     *
     *  - Médias (attachment) : ramenés à « upload_files ».
     *  - CPT cochés en admin (managed_post_types) : ces post types sont
     *    déclarés sans `map_meta_cap => true`, donc WordPress exige la cap
     *    singulière edit_post / delete_post / read_post que le rôle n'a pas.
     *    On les ramène à « edit_others_posts » (édition + suppression) et
     *    « read », ce qui revient à gérer ces CPT comme un mini-admin sans
     *    impacter les articles/pages natifs (absents de la liste).
     *
     * @param string[] $caps    Caps primitives résolues par WordPress.
     * @param string   $cap     Meta cap demandée.
     * @param int      $user_id Utilisateur testé.
     * @param array    $args    $args[0] = ID de l'objet visé.
     * @return string[]
     */
    public function remap_managed_post_type_caps(array $caps, string $cap, int $user_id, array $args): array
    {
        if (!in_array($cap, ['edit_post', 'delete_post', 'read_post'], true)) {
            return $caps;
        }

        if (empty($args[0]) || !user_can($user_id, self::ROLE_SLUG)) {
            return $caps;
        }

        $post = get_post($args[0]);
        if (!$post) {
            return $caps;
        }

        // Les médias partagent le capability_type « post » : on ramène les meta
        // caps à « upload_files », que le rôle possède déjà.
        if ($post->post_type === 'attachment') {
            return ['upload_files'];
        }

        if (!in_array($post->post_type, self::managed_post_types(), true)) {
            return $caps;
        }

        // Lecture → « read » ; édition et suppression → « edit_others_posts ».
        // Le rôle détient ces deux caps : le client peut donc modifier ET
        // supprimer les CPT sélectionnés, sans toucher aux articles/pages.
        return $cap === 'read_post' ? ['read'] : ['edit_others_posts'];
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
