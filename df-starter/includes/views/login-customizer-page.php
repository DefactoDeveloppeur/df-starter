<?php
if (!defined('ABSPATH')) {
    exit;
}

$bg_color   = get_option(DFS_Login_Customizer::OPT_BG_COLOR, DFS_Login_Customizer::DEFAULT_BG);
$text_color = get_option(DFS_Login_Customizer::OPT_TEXT_COLOR, DFS_Login_Customizer::DEFAULT_TEXT);
$logo_url   = esc_url(get_option(DFS_Login_Customizer::OPT_LOGO_URL, admin_url('images/w-logo-blue.png?ver=20131202')));
?>
<style>
    #login h1 a {
        background-image: url("<?php echo $logo_url; ?>");
    }
</style>
<div class="dfs-login-customizer page-container">
    <div class="customize-panel">
        <div class="wrap" style="margin-top: 0;">
            <h1><?php esc_html_e('Couleur de fond', 'df-starter'); ?></h1>
            <div class="dfs-actions-row">
                <button class="button" data-dfs-action="reset-bg"><?php esc_html_e('Réinitialiser', 'df-starter'); ?></button>
            </div>
            <label for="bgColorPicker" class="dfs-field-label"><?php esc_html_e('Couleur de fond de la page de connexion :', 'df-starter'); ?></label>
            <input type="color" id="bgColorPicker" value="<?php echo esc_attr($bg_color); ?>">
        </div>
        <div class="wrap" style="margin-top: 30px;">
            <h1><?php esc_html_e('Couleur du texte', 'df-starter'); ?></h1>
            <div class="dfs-actions-row">
                <button class="button" data-dfs-action="reset-text"><?php esc_html_e('Réinitialiser', 'df-starter'); ?></button>
            </div>
            <label for="bgTextColorPicker" class="dfs-field-label"><?php esc_html_e('Couleur du texte sur le fond de la page :', 'df-starter'); ?></label>
            <input type="color" id="bgTextColorPicker" value="<?php echo esc_attr($text_color); ?>">
        </div>
        <div class="wrap" style="margin-top: 50px;">
            <h1><?php esc_html_e('Choix du logo', 'df-starter'); ?></h1>
            <div class="dfs-actions-row">
                <button class="button button-primary" data-dfs-action="upload-logo"><?php esc_html_e('Téléverser un logo', 'df-starter'); ?></button>
                <button class="button" data-dfs-action="reset-logo"><?php esc_html_e('Réinitialiser', 'df-starter'); ?></button>
            </div>
            <div id="upload_image_preview" class="dfs-logo-preview">
                <img src="<?php echo $logo_url; ?>" alt="">
            </div>
        </div>
    </div>
    <div id="login-panel" class="login-panel" style="background-color: <?php echo esc_attr($bg_color); ?>;">
        <div class="wordpress-login-container login js login-action-login wp-core-ui locale-fr-fr">
            <h1 class="screen-reader-text"><?php esc_html_e('Se connecter', 'df-starter'); ?></h1>
            <div id="login">
                <h1 role="presentation" class="wp-login-logo"><a><?php esc_html_e('Propulsé par WordPress', 'df-starter'); ?></a></h1>
                <form>
                    <p>
                        <label for="user_login"><?php esc_html_e('Identifiant ou adresse e-mail', 'df-starter'); ?></label>
                        <input type="text" name="log" id="user_login" class="input" value="" size="20" autocomplete="username" />
                    </p>
                    <div class="user-pass-wrap">
                        <label for="user_pass"><?php esc_html_e('Mot de passe', 'df-starter'); ?></label>
                        <div class="wp-pwd">
                            <input type="password" name="pwd" id="user_pass" class="input password-input" value="" size="20" autocomplete="current-password" />
                        </div>
                    </div>
                    <p class="forgetmenot">
                        <input name="rememberme" type="checkbox" id="rememberme" value="forever" />
                        <label for="rememberme"><?php esc_html_e('Se souvenir de moi', 'df-starter'); ?></label>
                    </p>
                    <p class="submit">
                        <input type="button" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e('Se connecter', 'df-starter'); ?>" />
                    </p>
                </form>
                <p id="nav">
                    <a class="wp-login-lost-password" style="color: <?php echo esc_attr($text_color); ?>;"><?php esc_html_e('Mot de passe oublié ?', 'df-starter'); ?></a>
                </p>
                <p id="backtoblog">
                    <a style="color: <?php echo esc_attr($text_color); ?>;">&larr; <?php esc_html_e('Retour au site', 'df-starter'); ?></a>
                </p>
            </div>
        </div>
    </div>
</div>
