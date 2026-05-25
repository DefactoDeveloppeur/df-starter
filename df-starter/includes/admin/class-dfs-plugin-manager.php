<?php

if (!defined('ABSPATH')) {
    exit;
}

class DFS_Plugin_Manager
{
    const PAGE_SLUG = 'df-plugin-manager';
    const NONCE_ACTION = 'dfs_activate_plugin';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_menu', [$this, 'add_thickbox']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_dfs_activate_plugin', [$this, 'ajax_activate_plugin']);
    }

    public function add_thickbox(): void
    {
        add_thickbox();
    }

    public function add_settings_page(): void
    {
        add_submenu_page(
            DFS_Settings_Page::MENU_SLUG,
            __('Gestionnaire de plugin', 'df-starter'),
            __('Gestionnaire de plugin', 'df-starter'),
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
            'dfs-plugin-manager',
            DFS_URL . 'assets/js/plugin-manager.js',
            [],
            DFS_VERSION,
            true
        );

        wp_localize_script('dfs-plugin-manager', 'dfsPluginManager', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ]);
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        require DFS_PATH . 'includes/views/plugin-manager-page.php';
    }

    public static function recommended_plugins(): array
    {
        return [
            'advanced-custom-fields-pro' => [
                'name'          => 'Advanced Custom Fields PRO',
                'plugin_url'    => 'advanced-custom-fields-pro/acf.php',
                'author'        => 'WP Engine',
                'description'   => 'Personnalisez WordPress avec des champs intuitifs, puissants et professionnels.',
                'custom_dl_url' => 'https://www.advancedcustomfields.com/my-account/',
            ],
            'contact-form-7' => [
                'name'        => 'Contact Form 7',
                'plugin_url'  => 'contact-form-7/wp-contact-form-7.php',
                'author'      => 'Takayuki Miyoshi',
                'description' => 'Juste une autre extension de formulaire de contact. Simple mais souple d’utilisation.',
            ],
            'worker' => [
                'name'        => 'ManageWP',
                'plugin_url'  => 'worker/init.php',
                'author'      => 'Vladimir Prelovac',
                'description' => 'Une meilleure façon de gérer des dizaines de sites WordPress.',
            ],
            'seo-by-rank-math' => [
                'name'        => 'Rank Math SEO',
                'plugin_url'  => 'seo-by-rank-math/rank-math.php',
                'author'      => 'Rank Math SEO',
                'description' => 'Rank Math SEO est le meilleur plugin WordPress SEO avec les fonctionnalités de nombreux outils SEO et AI SEO dans un seul package pour aider à multiplier votre trafic SEO.',
            ],
            'secupress' => [
                'name'        => 'SecuPress',
                'plugin_url'  => 'secupress/secupress.php',
                'author'      => 'SecuPress',
                'description' => 'Plus qu’une extension, la garantie d’un site Web protégé par des experts.',
            ],
            'updraftplus' => [
                'name'        => 'UpdraftPlus',
                'plugin_url'  => 'updraftplus/updraftplus.php',
                'author'      => 'TeamUpdraft, DavidAnderson',
                'description' => 'Sauvegarde et restauration : sauvegarder localement, ou sur Amazon S3, Dropbox, Google Drive, Rackspace, (S)FTP, WebDAV & e-mail, en planification automatique.',
            ],
            'wp-optimize' => [
                'name'        => 'WPOptimize',
                'plugin_url'  => 'wp-optimize/wp-optimize.php',
                'author'      => 'TeamUpdraft, DavidAnderson',
                'description' => 'WP-Optimize rend votre site rapide et efficace. Il nettoie la base de données, compresse les images et met les pages en cache.',
            ],
        ];
    }

    public static function optional_plugins(): array
    {
        return [
            'better-search-replace' => [
                'name'        => 'Better Search Replace',
                'plugin_url'  => 'better-search-replace/better-search-replace.php',
                'author'      => 'WP Engine',
                'description' => 'Une petite extension pour effectuer des rechercher/remplacer dans votre base de données WordPress.',
            ],
            'password-protected' => [
                'name'        => 'Password Protected',
                'plugin_url'  => 'password-protected/password-protected.php',
                'author'      => 'Password Protected',
                'description' => 'Un moyen très simple pour protéger rapidement votre site WordPress avec un seul mot de passe.',
            ],
            'popup-maker' => [
                'name'        => 'Popup Maker',
                'plugin_url'  => 'popup-maker/popup-maker.php',
                'author'      => 'Popup Maker',
                'description' => 'Créez et stylisez facilement des fenêtres modales avec n’importe quel contenu.',
            ],
            'ameliabooking' => [
                'name'        => 'Amelia',
                'plugin_url'  => 'ameliabooking/ameliabooking.php',
                'author'      => 'TMS',
                'description' => 'Amelia est un outil de réservation automatisé simple mais puissant.',
            ],
            'woocommerce' => [
                'name'        => 'WooCommerce',
                'plugin_url'  => 'woocommerce/woocommerce.php',
                'author'      => 'Automattic',
                'description' => 'Tout ce dont vous avez besoin pour créer une boutique en ligne.',
            ],
        ];
    }

    public static function render_card($slug, array $plugin): void
    {
        $is_installed = file_exists(WP_PLUGIN_DIR . '/' . $plugin['plugin_url']);
        $is_active    = $is_installed && is_plugin_active($plugin['plugin_url']);
        ?>
        <div class="df_plugin-card" data-plugin="<?php echo esc_attr($slug); ?>">
            <div class="df_plugin-header-card">
                <div class="df_plugin-icon"><?php echo esc_html(mb_substr($plugin['name'], 0, 1)); ?></div>
                <div class="df_plugin-info"><h3><?php echo esc_html($plugin['name']); ?></h3></div>
            </div>
            <div class="df_plugin-description"><?php echo esc_html($plugin['description'] ?? ''); ?></div>
            <div class="df_plugin-meta">
                <span><?php printf(esc_html__('Par %s', 'df-starter'), esc_html($plugin['author'] ?? '')); ?></span>
                <span class="df_status-badge df_status-<?php echo $is_active ? 'active' : 'inactive'; ?>">
                    <?php echo $is_active ? esc_html__('Actif', 'df-starter') : esc_html__('Inactif', 'df-starter'); ?>
                </span>
            </div>
            <div class="df_plugin-actions">
                <?php if (!$is_installed) : ?>
                    <?php if (!empty($plugin['custom_dl_url'])) : ?>
                        <a href="<?php echo esc_url($plugin['custom_dl_url']); ?>"
                           class="df_btn df_btn-primary"
                           target="_blank" rel="noopener"
                           title="<?php echo esc_attr(sprintf(__('Plus d\'infos sur %s', 'df-starter'), $plugin['name'])); ?>">
                            <?php esc_html_e('Installer', 'df-starter'); ?>
                        </a>
                    <?php else : ?>
                        <a href="<?php echo esc_url(network_admin_url('plugin-install.php?tab=plugin-information&plugin=' . $slug . '&TB_iframe=true&width=600&height=550')); ?>"
                           class="thickbox df_btn df_btn-primary"
                           title="<?php echo esc_attr(sprintf(__('Plus d\'infos sur %s', 'df-starter'), $plugin['name'])); ?>">
                            <?php esc_html_e('Installer', 'df-starter'); ?>
                        </a>
                    <?php endif; ?>
                <?php else : ?>
                    <span class="df_btn df_btn-disable"><?php esc_html_e('Installé !', 'df-starter'); ?></span>
                    <?php if (!$is_active) :
                        $nonce = wp_create_nonce(self::NONCE_ACTION . '_' . $slug); ?>
                        <button class="df_btn df_btn-primary activate-plugin-btn"
                                type="button"
                                data-pluginpath="<?php echo esc_attr($plugin['plugin_url']); ?>"
                                data-slug="<?php echo esc_attr($slug); ?>"
                                data-nonce="<?php echo esc_attr($nonce); ?>"
                                title="<?php echo esc_attr(sprintf(__('Activer : %s', 'df-starter'), $plugin['name'])); ?>">
                            <?php esc_html_e('Activer', 'df-starter'); ?>
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public function ajax_activate_plugin(): void
    {
        if (!current_user_can('activate_plugins')) {
            wp_send_json_error('Permission refusée', 403);
        }

        $slug = isset($_POST['slug']) ? sanitize_key(wp_unslash($_POST['slug'])) : '';
        $nonce = $_POST['nonce'] ?? '';

        if (!$slug || !wp_verify_nonce($nonce, self::NONCE_ACTION . '_' . $slug)) {
            wp_send_json_error('Nonce invalide ou expiré', 403);
        }

        if (empty($_POST['pluginPath'])) {
            wp_send_json_error('Aucun plugin spécifié');
        }

        $plugin = sanitize_text_field(wp_unslash($_POST['pluginPath']));

        if (!function_exists('activate_plugin')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $result = activate_plugin($plugin);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success('Plugin activé');
    }
}
