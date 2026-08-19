<?php
if (!defined('ABSPATH')) {
    exit;
}

$menus  = DFS_Capabilities_Manager::manageable_menus();
$hidden = DFS_Roles::hidden_menus();
$active = isset($_GET['updated']);
?>
<div class="wrap">
    <div class="df_wp-admin-container">
        <div class="df_plugin-header">
            <h2><?php esc_html_e('Accès du rôle Client DF', 'df-starter'); ?></h2>
            <div class="subtitle">
                <?php esc_html_e('Le rôle Client DF dispose des mêmes droits que l\'administrateur, hors gestion des utilisateurs, des extensions, des thèmes et du coeur WordPress. Décochez les menus que les comptes Client DF ne doivent pas voir : leurs écrans seront aussi bloqués.', 'df-starter'); ?>
            </div>
        </div>

        <?php if ($active) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('Accès mis à jour.', 'df-starter'); ?></p>
            </div>
        <?php endif; ?>

        <?php if (empty($menus)) : ?>
            <div class="dfs-cap-empty">
                <p><?php esc_html_e('Aucun menu d\'administration détecté.', 'df-starter'); ?></p>
            </div>
        <?php else : ?>
            <div class="dfs-cap-toolbar">
                <input type="search"
                       id="dfs-cap-search"
                       class="dfs-cap-search"
                       placeholder="<?php esc_attr_e('Filtrer…', 'df-starter'); ?>" />
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=' . DFS_Capabilities_Manager::PAGE_SLUG)); ?>">
                <?php wp_nonce_field(DFS_Capabilities_Manager::NONCE_ACTION, DFS_Capabilities_Manager::NONCE_FIELD); ?>

                <div class="dfs-cap-groups">
                    <div class="dfs-cap-group">
                        <h3 class="dfs-cap-group-title">
                            <?php esc_html_e('Menus visibles pour Client DF', 'df-starter'); ?>
                            <span class="dfs-cap-count"><?php echo (int) count($menus); ?></span>
                        </h3>
                        <p class="description" style="margin: 0 0 .75em;">
                            <?php esc_html_e('Coché = accessible au client. Les menus Extensions, Apparence, Utilisateurs, Outils, Réglages et Defacto Starter sont toujours masqués.', 'df-starter'); ?>
                        </p>
                        <table class="wp-list-table widefat striped dfs-cap-table">
                            <thead>
                                <tr>
                                    <td class="dfs-cap-check check-column">
                                        <input type="checkbox"
                                               class="dfs-cap-group-toggle"
                                               title="<?php esc_attr_e('Tout cocher / décocher', 'df-starter'); ?>" />
                                    </td>
                                    <th scope="col"><?php esc_html_e('Menu', 'df-starter'); ?></th>
                                    <th scope="col"><?php esc_html_e('Identifiant technique', 'df-starter'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($menus as $slug => $label) :
                                    $id = 'dfs-menu-' . sanitize_html_class($slug); ?>
                                    <tr class="dfs-cap-item" data-cap="<?php echo esc_attr($slug . ' ' . $label); ?>">
                                        <th scope="row" class="dfs-cap-check check-column">
                                            <input type="hidden" name="dfs_menu_slugs[]" value="<?php echo esc_attr($slug); ?>" />
                                            <input type="checkbox"
                                                   id="<?php echo esc_attr($id); ?>"
                                                   class="dfs-cap-checkbox"
                                                   name="dfs_visible_menus[]"
                                                   value="<?php echo esc_attr($slug); ?>"
                                                   <?php checked(!in_array($slug, $hidden, true)); ?> />
                                        </th>
                                        <td>
                                            <label for="<?php echo esc_attr($id); ?>">
                                                <?php echo esc_html($label); ?>
                                            </label>
                                        </td>
                                        <td><code><?php echo esc_html($slug); ?></code></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php submit_button(__('Enregistrer les accès', 'df-starter')); ?>
            </form>
        <?php endif; ?>
    </div>
</div>
