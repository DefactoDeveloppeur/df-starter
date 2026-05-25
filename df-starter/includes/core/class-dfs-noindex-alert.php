<?php

if (!defined('ABSPATH')) {
    exit;
}

class DFS_Noindex_Alert
{
    public function register(): void
    {
        add_action('admin_init', [$this, 'check_noindex_status']);
        add_action('admin_head', [$this, 'noindex_warning_style']);
    }

    public function check_noindex_status(): void
    {
        if (!is_admin()) {
            return;
        }

        if (get_option('blog_public') === '0') {
            add_action('admin_notices', [$this, 'display_warning']);
        }
    }

    public function display_warning(): void
    {
        $settings_url = admin_url('options-reading.php');
        ?>
        <div class="notice notice-warning noindex-warning">
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

    public function noindex_warning_style(): void
    {
        if (!is_admin() || get_option('blog_public') !== '0') {
            return;
        }
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
