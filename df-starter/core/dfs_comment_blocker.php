<?php

if (!defined('ABSPATH')) {
    exit;
}

class CommentBlocker
{

    public function register(): void
    {
        // 1) Fermer globalement commentaires & pings
        add_filter('comments_open', '__return_false', 9999);
        add_filter('pings_open', '__return_false', 9999);

        // 2) Masquer totalement l'existence de commentaires
        add_filter('comments_array', '__return_empty_array', 9999); // au cas où un thème force l’affichage

        // 3) Couper l’endpoint REST de commentaires
        add_filter('rest_endpoints', [$this, 'removeRestCommentsEndpoint'], 9999);

        // 4) Bloquer les POST directs à wp-comments-post.php
        add_action('init', [$this, 'blockWpCommentsPostEarly'], 1);

        // 5) Couper XML-RPC (pingbacks & commentaires) sans tuer tout XML-RPC si tu l’utilises ailleurs
        add_filter('xmlrpc_methods', [$this, 'filterXmlrpcMethods'], 9999);

        // 6) Renvoyer 410 sur le flux des commentaires
        add_action('template_redirect', [$this, 'disableCommentsFeed'], 0);

        // 7) Nettoyage UI côté admin
        add_action('admin_menu', [$this, 'removeCommentsAdminMenu']);
        add_filter('dashboard_glance_items', [$this, 'removeAtAGlanceComments'], 9999);
        add_action('wp_before_admin_bar_render', [$this, 'removeAdminBarComments']);
        add_action('admin_init', [$this, 'hideDiscussionSettingsUI']);
    }

    public function removeRestCommentsEndpoint(array $endpoints): array
    {
        if (isset($endpoints['/wp/v2/comments'])) {
            unset($endpoints['/wp/v2/comments']);
        }
        return $endpoints;
    }

    public function blockWpCommentsPostEarly(): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if ($path && basename($path) === 'wp-comments-post.php') {
            // Bloque proprement
            status_header(403);
            nocache_headers();
            exit; // rien ne passe
        }

        // Par sécurité, tuer toute tentative d’insertion de commentaire côté core
        add_filter('preprocess_comment', function () {
            wp_die(__('Les commentaires sont fermés.', 'mon-plugin'), 403);
        }, 0);
    }

    public function filterXmlrpcMethods(array $methods): array
    {
        // Désactive le pingback et les méthodes de commentaires
        unset($methods['pingback.ping']);
        unset($methods['wp.newComment'], $methods['wp.editComment'], $methods['wp.deleteComment'], $methods['wp.getComment'], $methods['wp.getComments']);
        return $methods;
    }

    public function disableCommentsFeed(): void
    {
        if (is_comment_feed()) {
            // 410 Gone + message court
            status_header(410);
            header('Content-Type: text/plain; charset=' . get_bloginfo('charset'));
            echo 'Flux de commentaires désactivé.';
            exit;
        }
    }

    public function removeCommentsAdminMenu(): void
    {
        remove_menu_page('edit-comments.php');
    }

    public function removeAtAGlanceComments($items)
    {
        // Retire l’item "Commentaires" du widget "D’un coup d’œil"
        if (is_array($items)) {
            return array_filter($items, function ($item) {
                return (stripos(wp_strip_all_tags($item), 'comment') === false);
            });
        }
        return $items;
    }

    public function removeAdminBarComments(): void
    {
        global $wp_admin_bar;
        if ($wp_admin_bar) {
            $wp_admin_bar->remove_menu('comments');
        }
    }

    public function hideDiscussionSettingsUI(): void
    {
        // Optionnel : verrouille visuellement la page Réglages > Discussion
        // (ne touche pas aux options DB, on agit via hooks)
        add_filter('option_default_comment_status', '__return_false', 9999);
        add_filter('option_default_ping_status', '__return_false', 9999);
    }
}
