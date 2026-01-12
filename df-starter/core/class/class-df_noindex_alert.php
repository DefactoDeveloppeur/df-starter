<?php


Class DF_Noindex_Alert {

    public function __construct() {
        add_action('admin_init', [$this, 'check_noindex_status']);
        add_action('admin_head', [$this, 'noindex_warning_style']);
    }

    /**
     * Affiche une notification d'avertissement si le site est en noindex
     * À ajouter dans functions.php de votre thème ou dans un plugin
     */

    public function check_noindex_status() {
        // Vérifie si on est dans l'admin
        if (!is_admin()) {
            return;
        }

        // Récupère le paramètre de visibilité du site
        $blog_public = get_option('blog_public');

        // Si le site décourage les moteurs de recherche (noindex)
        if ($blog_public == '0') {
            add_action('admin_notices', [$this, 'display_noindex_warning']);
        }
    }

    public function display_noindex_warning() {
        $settings_url = admin_url('options-reading.php');
        ?>
        <div class="notice notice-warning">
            <p>
                <strong>⚠️ Attention :</strong>
                Votre site est configuré pour <strong>décourager les moteurs de recherche</strong> (noindex).
                Il n'apparaîtra pas dans les résultats Google.
            </p>
            <p>
                <a href="<?php echo esc_url($settings_url); ?>" class="button button-primary">
                    Modifier les paramètres de lecture
                </a>
            </p>
        </div>
        <?php
    }

    // Bonus : Ajouter un style personnalisé pour rendre la notification plus visible
   public function noindex_warning_style() {
        if (!is_admin()) {
            return;
        }

        $blog_public = get_option('blog_public');
        if ($blog_public == '0') {
            ?>
            <style>
                .notice-warning.noindex-warning {
                    border-left-color: #dc3232 !important;
                    background: #fff3cd;
                }
            </style>
            <?php
        }
    }
}
