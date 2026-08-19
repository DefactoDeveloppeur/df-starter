<?php

if (!defined('ABSPATH')) {
    exit;
}

class DFS_Admin_Cleanup
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'remove_menus_for_client_df'], 999);
        add_action('admin_menu', [$this, 'add_managed_post_type_menus'], 998);
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

        remove_menu_page('tools.php');
        remove_menu_page('edit-comments.php');
        remove_menu_page('plugins.php');
        remove_menu_page('themes.php');
        remove_menu_page('users.php');
        remove_menu_page('options-general.php');
        remove_submenu_page('index.php', 'update-core.php');
    }

    /**
     * Menu de repli pour les CPT cochés en admin dont le menu natif est
     * invisible pour le client : `show_in_menu => false` (le plugin gère son
     * menu à la main, souvent réservé à `manage_options`, ex. DF APIMMO) ou
     * accroché en sous-menu d'une page que le client ne voit pas. Sans cela,
     * le client a les droits sur le contenu mais aucun point d'entrée.
     */
    public function add_managed_post_type_menus(): void
    {
        if (!$this->should_restrict()) {
            return;
        }

        foreach (DFS_Roles::managed_post_types() as $slug) {
            $post_type = get_post_type_object($slug);
            if (!$post_type || !$post_type->show_ui) {
                continue;
            }

            // WordPress affiche déjà le menu natif dans ce cas.
            if ($post_type->show_in_menu === true) {
                continue;
            }

            $edit_cap = $post_type->cap->edit_posts ?? 'edit_posts';
            if (!current_user_can($edit_cap)) {
                continue;
            }

            $parent_slug = 'edit.php?post_type=' . $slug;

            add_menu_page(
                $post_type->labels->name,
                $post_type->labels->menu_name ?? $post_type->labels->name,
                $edit_cap,
                $parent_slug,
                '',
                $post_type->menu_icon ?: 'dashicons-admin-post',
                is_numeric($post_type->menu_position) ? (int) $post_type->menu_position : 26
            );

            add_submenu_page(
                $parent_slug,
                $post_type->labels->add_new_item ?? __('Ajouter', 'df-starter'),
                $post_type->labels->add_new ?? __('Ajouter', 'df-starter'),
                $post_type->cap->create_posts ?? $edit_cap,
                'post-new.php?post_type=' . $slug
            );
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

        $allowed = ['dashboard', 'edit', 'upload', 'media', 'edit.php?post_type=page', 'profile'];

        // CPT à capacités dédiées (capability_type personnalisé) : si le rôle
        // détient la cap d'édition du post type (accordée via la page « Accès
        // Client DF »), ses écrans sont autorisés.
        $post_type_object = $screen->post_type ? get_post_type_object($screen->post_type) : null;
        $has_custom_edit_cap = $post_type_object
            && !in_array($post_type_object->cap->edit_posts ?? '', ['edit_posts', 'edit_pages', ''], true)
            && current_user_can($post_type_object->cap->edit_posts);

        $is_allowed = in_array($screen->base, $allowed, true)
            || $screen->post_type === 'page'
            || $screen->post_type === 'post'
            || $screen->post_type === 'attachment'
            || post_type_supports($screen->post_type, 'editor')
            // CPT cochés en admin : on autorise leurs écrans (liste, édition,
            // ajout) même s'ils ne supportent pas l'éditeur de contenu.
            || in_array($screen->post_type, DFS_Roles::managed_post_types(), true)
            || $has_custom_edit_cap;

        if (!$is_allowed) {
            wp_safe_redirect(admin_url('profile.php'));
            exit;
        }
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
    }

    public function remove_posts_columns($columns, $post_type)
    {
        unset($columns['comments']);
        return $columns;
    }
}
