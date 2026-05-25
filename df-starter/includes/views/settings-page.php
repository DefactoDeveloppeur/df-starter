<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <div class="df_wp-admin-container">
        <div class="df_plugin-header">
            <h2><?php esc_html_e('Defacto Starter - Paramètres', 'df-starter'); ?></h2>
            <div class="subtitle"><?php esc_html_e('Options du site', 'df-starter'); ?></div>
        </div>

        <form method="post" action="options.php">
            <?php settings_fields(DFS_Settings_Page::OPTION_GROUP); ?>
            <?php do_settings_sections(DFS_Settings_Page::OPTION_GROUP); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Afficher le custom post type "Projets" installé par Divi', 'df-starter'); ?></th>
                    <td>
                        <input type="checkbox"
                               name="<?php echo esc_attr(DFS_Settings_Page::OPTION_SHOW_PROJETS); ?>"
                               value="1"
                               <?php checked(1, get_option(DFS_Settings_Page::OPTION_SHOW_PROJETS), true); ?> />
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <div class="df_notification" id="notification"></div>
</div>
