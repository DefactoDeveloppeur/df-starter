<?php

if (!defined('ABSPATH')) {
    exit;
}

class DFS_Admin_Cleanup
{
    /**
     * Menus top-level toujours masqués pour le client, en plus de ceux
     * décochés dans « Accès Client DF ». Les écrans correspondants sont aussi
     * bloqués dans restrict_admin_access() : le masquage seul ne suffit pas.
     */
    const FORCED_HIDDEN_MENUS = [
        'tools.php',
        'edit-comments.php',
        'plugins.php',
        'themes.php',
        'users.php',
        'options-general.php',
        // Réglages du starter : jamais pour le client, même avec manage_options.
        'df-settings',
    ];

    /**
     * Bases d'écrans core interdites au client. Le rôle n'a pas les caps
     * « admin » pour la plupart (extensions, thèmes, utilisateurs…), mais
     * manage_options ouvrirait les réglages : on bloque explicitement.
     */
    const BLOCKED_SCREEN_BASES = [
        'plugins', 'plugin-install', 'plugin-editor',
        'themes', 'theme-install', 'theme-editor', 'site-editor',
        'customize', 'nav-menus', 'widgets',
        'users', 'user-new', 'user-edit', 'user',
        'tools', 'import', 'export', 'site-health',
        'export-personal-data', 'erase-personal-data',
        'update-core', 'update',
        'options-general', 'options-writing', 'options-reading',
        'options-discussion', 'options-media', 'options-permalink',
        'options-privacy',
        'edit-comments', 'comment',
    ];

    public function register(): void
    {
        add_action('admin_menu', [$this, 'remove_menus_for_client_df'], 999);
        add_action('current_screen', [$this, 'restrict_admin_access']);
        add_action('admin_init', [$this, 'hide_updates']);
        add_action('admin_bar_menu', [$this, 'customize_admin_bar'], 999);
        add_filter('use_block_editor_for_post', '__return_false', 10);
        add_filter('manage_posts_columns', [$this, 'remove_posts_columns'], 10, 2);
    }

    private function should_restrict(): bool
    {
        return current_user_can(DFS_Roles::ROLE_SLUG) && !is_super_admin();
    }

    public function remove_menus_for_client_df(): void
    {
        if (!$this->should_restrict()) {
            return;
        }

        foreach (self::FORCED_HIDDEN_MENUS as $slug) {
            remove_menu_page($slug);
        }
        remove_submenu_page('index.php', 'update-core.php');

        // Menus décochés dans « Accès Client DF » (le blocage réel des écrans
        // est fait dans restrict_admin_access).
        foreach (DFS_Roles::hidden_menus() as $slug) {
            remove_menu_page($slug);
        }
    }

    public function restrict_admin_access(): void
    {
        if (!$this->should_restrict()) {
            return;
        }

        // Ne jamais interférer avec les requêtes AJAX ni les endpoints d'upload :
        // elles attendent du JSON/texte, un wp_safe_redirect() casserait la
        // réponse et l'uploader afficherait « HTTP error ». L'upload média passe
        // par async-upload.php / media-upload.php, dont le screen base
        // (« async-upload ») n'est pas une page d'admin à restreindre.
        if (wp_doing_ajax() || in_array($GLOBALS['pagenow'] ?? '', ['async-upload.php', 'media-upload.php'], true)) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen) {
            return;
        }

        $is_blocked = in_array($screen->base, self::BLOCKED_SCREEN_BASES, true)
            // options.php en GET liste et édite toutes les options du site. En
            // POST il sert de handler aux réglages des plugins (Settings API) :
            // on le laisse passer, chaque option_page étant protégée par nonce.
            || ($screen->base === 'options' && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST')
            || $this->is_hidden_menu_screen($screen);

        if ($is_blocked) {
            wp_safe_redirect(admin_url('profile.php'));
            exit;
        }
    }

    /**
     * L'écran courant appartient-il à un menu masqué pour le client (décoché
     * en admin ou toujours masqué) ?
     */
    private function is_hidden_menu_screen(WP_Screen $screen): bool
    {
        $hidden = array_merge(DFS_Roles::hidden_menus(), self::FORCED_HIDDEN_MENUS);

        // Menu de CPT : son slug de menu est « edit.php?post_type=x ».
        if ($screen->post_type && in_array('edit.php?post_type=' . $screen->post_type, $hidden, true)) {
            return true;
        }

        // Articles : le slug du menu est « edit.php » sans post_type, alors que
        // ses écrans (liste, édition, catégories…) portent le post type « post ».
        if ($screen->post_type === 'post' && in_array('edit.php', $hidden, true)) {
            return true;
        }

        // Médias : slug de menu « upload.php », écrans « upload » (bibliothèque),
        // « media » (media-new.php) et édition d'un fichier (post type attachment).
        if (in_array('upload.php', $hidden, true)
            && (in_array($screen->base, ['upload', 'media'], true) || $screen->post_type === 'attachment')) {
            return true;
        }

        // Page déclarée via add_menu_page/add_submenu_page : on compare le slug
        // de la page et celui de son menu parent.
        $plugin_page = $GLOBALS['plugin_page'] ?? null;
        if ($plugin_page) {
            if (in_array($plugin_page, $hidden, true)) {
                return true;
            }

            $parent = function_exists('get_admin_page_parent') ? get_admin_page_parent() : '';
            if ($parent && in_array($parent, $hidden, true)) {
                return true;
            }
        }

        return false;
    }

    public function hide_updates(): void
    {
        if (!$this->should_restrict()) {
            return;
        }

        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');
        remove_action('admin_notices', 'update_nag', 3);
        add_filter('pre_site_transient_update_plugins', '__return_null');
        add_filter('pre_site_transient_update_themes', '__return_null');
        add_filter('pre_site_transient_update_core', '__return_null');
    }

    public function customize_admin_bar($wp_admin_bar): void
    {
        if (!$this->should_restrict()) {
            return;
        }

        $wp_admin_bar->remove_node('updates');
        $wp_admin_bar->remove_node('wp-logo');
        $wp_admin_bar->remove_node('comments');

        // Retire tout noeud pointant vers une page d'admin masquée ou bloquée
        // (« + Nouveau » d'un CPT masqué, raccourcis ajoutés par les plugins…).
        // La barre s'affiche aussi côté front : on se base sur les URLs.
        $removed = [];

        foreach ((array) $wp_admin_bar->get_nodes() as $node) {
            if (!empty($node->href) && $this->links_to_hidden_admin_page((string) $node->href)) {
                $wp_admin_bar->remove_node($node->id);
                $removed[$node->id] = true;
            }
        }

        // Retire aussi les descendants des noeuds supprimés : un enfant orphelin
        // serait sinon raccroché à la racine de la barre au rendu.
        do {
            $changed = false;
            foreach ((array) $wp_admin_bar->get_nodes() as $node) {
                if (!empty($node->parent) && isset($removed[$node->parent])) {
                    $wp_admin_bar->remove_node($node->id);
                    $removed[$node->id] = true;
                    $changed = true;
                }
            }
        } while ($changed);
    }

    /**
     * Le lien pointe-t-il vers une page d'admin interdite au client (écran core
     * bloqué, menu masqué, CPT masqué) ?
     */
    private function links_to_hidden_admin_page(string $href): bool
    {
        $admin_base = admin_url();
        $admin_path = (string) parse_url($admin_base, PHP_URL_PATH);

        // Certains plugins mettent des hrefs relatifs (« /wp-admin/… ») dans
        // la barre d'admin : on les accepte au même titre que l'URL complète.
        if (strpos($href, $admin_base) === 0) {
            $relative = substr($href, strlen($admin_base));
        } elseif ($admin_path !== '' && strpos($href, $admin_path) === 0) {
            $relative = substr($href, strlen($admin_path));
        } else {
            return false;
        }
        $path     = (string) (parse_url($relative, PHP_URL_PATH) ?: '');

        $query = [];
        parse_str((string) parse_url($relative, PHP_URL_QUERY), $query);

        // Fichiers core dont l'écran est bloqué (plugins.php, options-*.php…).
        foreach (self::BLOCKED_SCREEN_BASES as $base) {
            if ($path === $base . '.php') {
                return true;
            }
        }

        $hidden = array_merge(DFS_Roles::hidden_menus(), self::FORCED_HIDDEN_MENUS);

        if ($path !== '' && in_array($path, $hidden, true)) {
            return true;
        }

        // Articles masqués (slug « edit.php ») : couvre aussi « + Nouveau →
        // Article » (post-new.php), dont l'URL ne porte pas de post_type
        // explicite (défaut « post »).
        if (in_array('edit.php', $hidden, true) && $path === 'post-new.php' && empty($query['post_type'])) {
            return true;
        }

        // « Modifier » (post.php?post=X) : l'URL ne dit pas le type de contenu,
        // on le résout depuis l'ID pour rattacher le lien à son menu.
        if ($path === 'post.php' && !empty($query['post'])) {
            $type = get_post_type((int) $query['post']);
            if ($type) {
                $menu = ($type === 'post') ? 'edit.php' : 'edit.php?post_type=' . $type;
                if (in_array($menu, $hidden, true)) {
                    return true;
                }
            }
        }

        // Médias masqués (slug « upload.php ») : couvre « + Nouveau → Fichier
        // média » (media-new.php).
        if (in_array('upload.php', $hidden, true) && $path === 'media-new.php') {
            return true;
        }

        // Page de plugin : admin.php?page=slug.
        if ($path === 'admin.php' && !empty($query['page']) && in_array($query['page'], $hidden, true)) {
            return true;
        }

        // CPT masqué : edit.php?post_type=x, post-new.php?post_type=x…
        if (!empty($query['post_type'])
            && in_array('edit.php?post_type=' . $query['post_type'], $hidden, true)) {
            return true;
        }

        return false;
    }

    public function remove_posts_columns($columns, $post_type)
    {
        unset($columns['comments']);
        return $columns;
    }
}
