<?php
if (!defined('ABSPATH')) {
    exit;
}

$caps        = DFS_Roles::detect_plugin_caps();
$groups      = DFS_Capabilities_Manager::group_caps($caps);
$granted     = (array) get_option(DFS_Roles::OPTION_CAPS, []);
$post_types  = DFS_Roles::detect_eligible_post_types();
$granted_pts = (array) get_option(DFS_Roles::OPTION_POST_TYPES, []);
$active      = isset($_GET['updated']);
$has_content = !empty($caps) || !empty($post_types);
?>
<div class="wrap">
    <div class="df_wp-admin-container">
        <div class="df_plugin-header">
            <h2><?php esc_html_e('Accès du rôle Client DF', 'df-starter'); ?></h2>
            <div class="subtitle">
                <?php esc_html_e('Choisissez les fonctionnalités des plugins et les types de contenu accessibles aux comptes Client DF.', 'df-starter'); ?>
            </div>
        </div>

        <?php if ($active) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('Accès mis à jour.', 'df-starter'); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!$has_content) : ?>
            <div class="dfs-cap-empty">
                <p>
                    <?php esc_html_e('Aucune capacité de plugin ni type de contenu personnalisé n\'a été détecté.', 'df-starter'); ?>
                </p>
                <p>
                    <?php esc_html_e('Activez un plugin (Amelia, WooCommerce, etc.) ou enregistrez un custom post type, puis revenez sur cette page : les options apparaîtront ici, refusées par défaut.', 'df-starter'); ?>
                </p>
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

                <?php if (!empty($post_types)) : ?>
                    <div class="dfs-cap-groups">
                        <div class="dfs-cap-group">
                            <h3 class="dfs-cap-group-title">
                                <?php esc_html_e('Types de contenu (CPT)', 'df-starter'); ?>
                                <span class="dfs-cap-count"><?php echo (int) count($post_types); ?></span>
                            </h3>
                            <p class="description" style="margin: 0 0 .75em;">
                                <?php esc_html_e('Autorise le rôle Client DF à créer, modifier et supprimer ces types de contenu, sans toucher aux articles ni aux pages.', 'df-starter'); ?>
                            </p>
                            <table class="wp-list-table widefat striped dfs-cap-table">
                                <thead>
                                    <tr>
                                        <td class="dfs-cap-check check-column">
                                            <input type="checkbox"
                                                   class="dfs-cap-group-toggle"
                                                   title="<?php esc_attr_e('Tout cocher / décocher', 'df-starter'); ?>" />
                                        </td>
                                        <th scope="col"><?php esc_html_e('Type de contenu', 'df-starter'); ?></th>
                                        <th scope="col"><?php esc_html_e('Identifiant technique', 'df-starter'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($post_types as $slug => $post_type) :
                                        $id    = 'dfs-pt-' . sanitize_html_class($slug);
                                        $label = $post_type->labels->singular_name ?? $post_type->label ?? $slug; ?>
                                        <tr class="dfs-cap-item" data-cap="<?php echo esc_attr($slug . ' ' . $label); ?>">
                                            <th scope="row" class="dfs-cap-check check-column">
                                                <input type="checkbox"
                                                       id="<?php echo esc_attr($id); ?>"
                                                       class="dfs-cap-checkbox"
                                                       name="dfs_post_types[]"
                                                       value="<?php echo esc_attr($slug); ?>"
                                                       <?php checked(in_array($slug, $granted_pts, true)); ?> />
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
                <?php endif; ?>

                <?php if (!empty($caps)) : ?>
                    <div class="dfs-cap-groups">
                        <?php foreach ($groups as $key => $group_caps) : ?>
                            <div class="dfs-cap-group">
                                <h3 class="dfs-cap-group-title">
                                    <?php echo esc_html(DFS_Capabilities_Manager::friendly_group($key)); ?>
                                    <span class="dfs-cap-count"><?php echo (int) count($group_caps); ?></span>
                                </h3>
                                <table class="wp-list-table widefat striped dfs-cap-table">
                                    <thead>
                                        <tr>
                                            <td class="dfs-cap-check check-column">
                                                <input type="checkbox"
                                                       class="dfs-cap-group-toggle"
                                                       title="<?php esc_attr_e('Tout cocher / décocher', 'df-starter'); ?>" />
                                            </td>
                                            <th scope="col"><?php esc_html_e('Capacité', 'df-starter'); ?></th>
                                            <th scope="col"><?php esc_html_e('Identifiant technique', 'df-starter'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($group_caps as $cap) :
                                            $id = 'dfs-cap-' . sanitize_html_class($cap); ?>
                                            <tr class="dfs-cap-item" data-cap="<?php echo esc_attr($cap); ?>">
                                                <th scope="row" class="dfs-cap-check check-column">
                                                    <input type="checkbox"
                                                           id="<?php echo esc_attr($id); ?>"
                                                           class="dfs-cap-checkbox"
                                                           name="dfs_caps[]"
                                                           value="<?php echo esc_attr($cap); ?>"
                                                           <?php checked(!empty($granted[$cap])); ?> />
                                                </th>
                                                <td>
                                                    <label for="<?php echo esc_attr($id); ?>">
                                                        <?php echo esc_html(DFS_Capabilities_Manager::cap_label($cap)); ?>
                                                    </label>
                                                </td>
                                                <td><code><?php echo esc_html($cap); ?></code></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php submit_button(__('Enregistrer les accès', 'df-starter')); ?>
            </form>
        <?php endif; ?>
    </div>
</div>
