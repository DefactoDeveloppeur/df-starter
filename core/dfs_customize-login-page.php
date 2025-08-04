<?php



class DFS_CustomizePlugin {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('login_enqueue_scripts', [$this, 'custom_login_css']);
        add_action('wp_ajax_update_login_color', [$this, 'handle_update_login_color']);
        add_action('wp_ajax_update_login_bg_text_color', [$this, 'handle_update_login_bg_text_color']);
        add_action('wp_ajax_update_login_logo_image', [$this, 'handle_update_login_logo_image']);
        add_action('admin_enqueue_scripts', [$this, 'myplugin_enqueue_media_uploader']);
    }

    function myplugin_enqueue_media_uploader($hook) {
        if ($hook !== 'defacto-starter_page_df-customize') {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_script(
            'myplugin-media-uploader',
            MY_PLUGIN_URL . 'assets/js/myplugin-media-uploader.js',
            ['jquery'],
            '1.0',
            true
        );
        wp_localize_script('myplugin-media-uploader', 'myplugin_ajax', [
            'ajax_url' => admin_url('admin-ajax.php')
        ]);

        wp_enqueue_script(
            'df-customize-login-page', 
            MY_PLUGIN_URL . 'assets/js/df-customize-login-page.js',
            ['jquery'], 
            '1.0', 
            true
        );
        wp_localize_script('df-customize-login-page', 'myplugin_ajax', [
            'ajax_url'    => admin_url('admin-ajax.php'),
            'default_url' => admin_url('images/w-logo-blue.png?ver=20131202')
        ]);
    }

    public function add_settings_page()
    {
        add_submenu_page(
            'df-settings',
            'Modifier page de connexion',
            'Modifier page de connexion',
            'manage_options',
            'df-customize-login-page',
            [$this, 'render_customize_page']
        );
    }

    public function render_customize_page() {

        $login_background_color = get_option('login_bg_color', '#f0f0f1');
        $login_text_color = get_option('login_bg_text_color', '#50575e');
        $login_logo_url = esc_url(get_option('login_logo_url', admin_url('images/w-logo-blue.png?ver=20131202')));

        ?>
        <style>
            /* Container holding both sides */
            .page-container {
            display: flex;
            gap: 20px;
            padding: 20px;
            box-sizing: border-box;
            max-width: 1200px;
            margin: 0 auto;
            }
            /* Left customize panel */
            .customize-panel {
            flex: 1;
            border: 1px solid #ccc;
            padding: 20px;
            border-radius: 6px;
            background: #fafafa;
            max-width: 600px;
            box-sizing: border-box;
            }
            /* Right login panel */
            .login-panel {
            flex: 1;
            border: 1px solid #ccc;
            padding: 20px;
            border-radius: 6px;
            background: #fff;
            max-width: 600px;
            box-sizing: border-box;
            }
            /* For mobile: stack vertically */
            @media (max-width: 1280px) {
              .page-container {
                flex-direction: column;
                padding: 10px;
              }
              .customize-panel, .login-panel {
                max-width: 100%;
                width: 100%;
                margin-bottom: 20px;
              }
              .login-panel {
                min-height: 800px; /* a bit smaller on mobile if you want */
              }

              .login-panel iframe {
                min-height: 800px; /* a bit smaller on mobile if you want */
              }
            }

            /* Optional: consistent h1 style */
            .customize-panel h1, .login-panel h1 {
            margin-bottom: 20px;
            }

            #login h1 a {
                background-image: url("<?=$login_logo_url?>");
            }
        </style>
        <div class="page-container">
            <div class="customize-panel">
                <div class="wrap" style="margin-top: 0;">
                    <h1>Changement de couleur</h1>
                    <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                        <button id="reset_color_button" onclick="set_default_bg_color()" class="button">Réinitialiser</button>
                    </div>
                    <label for="bgColorPicker" style="font-weight: bold; display: block; margin-bottom: 10px;">Couleur de fond de la page de connexion :</label>
                    <input type="color" id="bgColorPicker" oninput="background_pick(this)" value="<?=$login_background_color?>" style="width: 100%; height: 50px; border: none; cursor: pointer;">
                </div>
                <div class="wrap" style="margin-top: 30px;">
                    <h1>Changement de couleur</h1>
                    <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                        <button id="reset_color_button" onclick="set_default_bg_text_color()" class="button">Réinitialiser</button>
                    </div>
                    <label for="bgTextColorPicker" style="font-weight: bold; display: block; margin-bottom: 10px;">Couleur du texte sur le fond de la page :</label>
                    <input type="color" id="bgTextColorPicker" oninput="background_text_pick(this)" value="<?=$login_text_color?>" style="width: 100%; height: 50px; border: none; cursor: pointer;">
                </div>
                <div class="wrap" style="margin-top: 50px;">
                    <h1>Choix du logo</h1>
                    <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                        <button id="upload_image_button" onclick="upload_image(event)" class="button button-primary">Téléverser un logo</button>
                        <button id="reset_image_button" onclick="set_default_logo()" class="button">Réinitialiser</button>
                    </div>
                    <div id="upload_image_preview" style="border: 1px solid #ccc; padding: 15px; background: #fafafa; border-radius: 4px; max-width: 100%; text-align: center;">
                        <img src="<?=$login_logo_url?>" style="max-width: 100%; height: auto; max-height: 200px; object-fit: contain; display: inline-block;" />
                    </div>
                </div>
            </div>
            <div id="login-panel" class="login-panel" style="background-color: <?=$login_background_color?>;">
                <div class="wordpress-login-container login js login-action-login wp-core-ui locale-fr-fr">

                    <link rel='stylesheet' href='https://bas.defacto.ovh/wp-content/plugins/wp-booking-system/assets/css/style-front-end-form.min.css?ver=2.0.19.12' type='text/css' media='all' />
                    <link rel='stylesheet' href='https://bas.defacto.ovh/wp-includes/css/dashicons.min.css?ver=6.8.1' type='text/css' media='all' />
                    <link rel='stylesheet' href='https://bas.defacto.ovh/wp-includes/css/buttons.min.css?ver=6.8.1' type='text/css' media='all' />
                    <link rel='stylesheet' href='https://bas.defacto.ovh/wp-admin/css/forms.min.css?ver=6.8.1' type='text/css' media='all' />
                    <link rel='stylesheet' href='https://bas.defacto.ovh/wp-admin/css/l10n.min.css?ver=6.8.1' type='text/css' media='all' />
                    <link rel='stylesheet' href='https://bas.defacto.ovh/wp-admin/css/login.min.css?ver=6.8.1' type='text/css' media='all' />
                    
                    <h1 class="screen-reader-text">Se connecter</h1>
                    <div id="login">
                        <h1 role="presentation" class="wp-login-logo"><a>Propulsé par WordPress</a></h1>

                        <form>
                            <p>
                                <label for="user_login">Identifiant ou adresse e-mail</label>
                                <input type="text" name="log" id="user_login" class="input" value="" size="20" autocapitalize="off" autocomplete="username" required="required" />
                            </p>

                            <div class="user-pass-wrap">
                                <label for="user_pass">Mot de passe</label>
                                <div class="wp-pwd">
                                    <input type="password" name="pwd" id="user_pass" class="input password-input" value="" size="20" autocomplete="current-password" spellcheck="false" required="required" />
                                    <button type="button" class="button button-secondary wp-hide-pw hide-if-no-js" data-toggle="0" aria-label="Afficher le mot de passe">
                                        <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                                    </button>
                                </div>
                            </div>
                            <p class="forgetmenot"><input name="rememberme" type="checkbox" id="rememberme" value="forever"  /> <label for="rememberme">Se souvenir de moi</label></p>
                            <p class="submit">
                                <input type="button" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="Se connecter" />
                            </p>
                        </form>

                        <p id="nav">
                            <a class="wp-login-lost-password" style="color: <?=$login_text_color?>;">Mot de passe oublié ?</a>
                        </p>

                        <p id="backtoblog">
                            <a style="color: <?=$login_text_color?>;">&larr; Aller sur Bac à sabelette</a>
                        </p>
                        <div class="privacy-policy-page-link"><p class="privacy-policy-link" rel="privacy-policy">Politique de confidentialité</p></div>
                    </div>

                    <div class="language-switcher">
                        <form id="language-switcher" method="get">
                            <label for="language-switcher-locales">
                                <span class="dashicons dashicons-translation" aria-hidden="true"></span>
                                <span class="screen-reader-text">Langue</span>
                            </label>

                            <select name="wp_lang" id="language-switcher-locales">
                                <option value="en_US" lang="en" data-installed="1">English (United States)</option>
                                <option value="es_ES" lang="es" data-installed="1">Español</option>
                                <option value="fr_FR" lang="fr" selected='selected' data-installed="1">Français</option>
                            </select>
                            
                            <input type="button" class="button" value="Modifier">
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function handle_update_login_color()
    {
        if (isset($_POST['color'])) {
            update_option('login_bg_color', sanitize_hex_color($_POST['color']));
            wp_send_json_success(['message' => 'Background color updated successfully']);
            wp_die();
        }

        wp_send_json_error(['message' => 'Failed to update background color']);
        wp_die();
    }

    public function handle_update_login_bg_text_color()
    {
        if (isset($_POST['color'])) {
            update_option('login_bg_text_color', sanitize_hex_color($_POST['color']));
            wp_send_json_success(['message' => 'Background color updated successfully']);
            wp_die();
        }

        wp_send_json_error(['message' => 'Failed to update background color']);
        wp_die();
    }

    public function handle_update_login_logo_image()
    {
        if (isset($_POST['url'])) {
            update_option('login_logo_url', $_POST['url']);
            wp_send_json_success(['message' => 'Logo updated successfully']);
            wp_die();
        }

        wp_send_json_error(['message' => 'Failed to update logo']);
        wp_die();
    }
    
    public function custom_login_css() {
        $bg_color = get_option('login_bg_color', '#f0f0f1');
        $logo_url = get_option('login_logo_url', admin_url('images/w-logo-blue.png?ver=20131202'));
        $bg_text_color = get_option('login_bg_text_color', '#50575e');
        ?>
        <style type="text/css">
            body.login {
                background-color: <?=esc_attr($bg_color)?> !important;
            }

            .login h1 a {
                background-image: url('<?= esc_url($logo_url) ?>')  !important;
            }

            #nav a,
            #backtoblog a {
                color: <?=esc_attr($bg_text_color)?> !important;
            }
        </style>
        <?php
    }
}