<?php
if (!defined('ABSPATH')) {
    exit;
}

$caps    = DFS_Roles::detect_plugin_caps();
$groups  = DFS_Capabilities_Manager::group_caps($caps);
$granted = (array) get_option(DFS_Roles::OPTION_CAPS, []);
$active  = isset($_GET['updated']);
?>
<div class="wrap">
    <div class="df_wp-admin-container">
        <div class="df_plugin-header">
            <h2><?php esc_html_e('Accès du rôle Client DF', 'df-starter'); ?></h2>
            <div class="subtitle">
                <?php esc_html_e('Choisissez les fonctionnalités des plugins accessibles aux comptes Client DF.', 'df-starter'); ?>
            </div>
        </div>

        <?php if ($active) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('Accès mis à jour.', 'df-starter'); ?></p>
            </div>
        <?php endif; ?>

        <?php if (empty($caps)) : ?>
            <div class="dfs-cap-empty">
                <p>
                    <?php esc_html_e('Aucune capacité spécifique à un plugin n\'a été détectée.', 'df-starter'); ?>
                </p>
                <p>
                    <?php esc_html_e('Activez un plugin (Amelia, WooCommerce, etc.) puis revenez sur cette page : ses options apparaîtront ici, refusées par défaut.', 'df-starter'); ?>
                </p>
            </div>
        <?php else : ?>
            <div class="dfs-cap-toolbar">
                <input type="search"
                       id="dfs-cap-search"
                       class="dfs-cap-search"
                       placeholder="<?php esc_attr_e('Filtrer les capacités…', 'df-starter'); ?>" />
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=' . DFS_Capabilities_Manager::PAGE_SLUG)); ?>">
                <?php wp_nonce_field(DFS_Capabilities_Manager::NONCE_ACTION, DFS_Capabilities_Manager::NONCE_FIELD); ?>

                <div class="dfs-cap-groups">
                    <?php foreach ($groups as $key => $group_caps) : ?>
                        <div class="dfs-cap-group">
                            <div class="dfs-cap-group-head">
                                <h3><?php echo esc_html(DFS_Capabilities_Manager::friendly_group($key)); ?></h3>
                                <label class="dfs-cap-toggle-all">
                                    <input type="checkbox" class="dfs-cap-group-toggle" />
                                    <span><?php esc_html_e('Tout cocher', 'df-starter'); ?></span>
                                </label>
                            </div>
                            <div class="dfs-cap-list">
                                <?php foreach ($group_caps as $cap) : ?>
                                    <label class="dfs-cap-item" data-cap="<?php echo esc_attr($cap); ?>">
                                        <input type="checkbox"
                                               class="dfs-cap-checkbox"
                                               name="dfs_caps[]"
                                               value="<?php echo esc_attr($cap); ?>"
                                               <?php checked(!empty($granted[$cap])); ?> />
                                        <span class="dfs-cap-name"><?php echo esc_html(DFS_Capabilities_Manager::cap_label($cap)); ?></span>
                                        <code class="dfs-cap-slug"><?php echo esc_html($cap); ?></code>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php submit_button(__('Enregistrer les accès', 'df-starter')); ?>
            </form>
        <?php endif; ?>
    </div>
</div>
