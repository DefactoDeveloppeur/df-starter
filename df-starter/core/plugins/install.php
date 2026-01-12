<?php
// Afficher des plugins recommandés avec liens vers le marketplace
function recommander_plugins() {
    $plugins_recommandes = [
        'advanced-custom-fields-pro' => [
            'name' => 'Advanced Custom Fields PRO',
            'plugin_url' => 'advanced-custom-fields-pro/acf.php',
            'author' => 'WP Engine',
            'description' => 'Personnalisez WordPress avec des champs intuitifs, puissants et professionnels.',
            'custom_dl_url' => 'https://www.advancedcustomfields.com/my-account/',
        ],
        'contact-form-7' => [
            'name' => 'Contact Form 7',
            'plugin_url' => 'contact-form-7/wp-contact-form-7.php',
            'author' => 'Takayuki Miyoshi',
            'description' => 'Juste une autre extension de formulaire de contact. Simple mais souple d’utilisation.'
        ],
        'worker' => [
            'name' => 'ManageWP',
            'plugin_url' => 'worker/init.php',
            'author' => 'Vladimir Prelovac',
            'description' => 'Une meilleure façon de gérer des dizaines de sites WordPress.'
        ],
        'seo-by-rank-math' => [
            'name' => 'Rank Math SEO',
            'plugin_url' => 'seo-by-rank-math/rank-math.php',
            'author' => 'Rank Math SEO',
            'description' => 'Rank Math SEO est le meilleur plugin WordPress SEO avec les fonctionnalités de nombreux outils SEO et AI SEO dans un seul package pour aider à multiplier votre trafic SEO.'
        ],
        'secupress' => [
            'name' => 'SecuPress',
            'plugin_url' => 'secupress/secupress.php',
            'author' => 'SecuPress',
            'description' => 'Plus qu’une extension, la garantie d’un site Web protégé par des experts.'
        ],
        'updraftplus' => [
            'name' => 'UpdraftPlus',
            'plugin_url' => 'updraftplus/updraftplus.php',
            'author' => 'TeamUpdraft, DavidAnderson',
            'description' => 'Sauvegarde et restauration : sauvegarder localement, ou sur Amazon S3, Dropbox, Google Drive, Rackspace, (S)FTP, WebDAV & e-mail, en planification automatique.'
        ],
        'wp-optimize' => [
            'name' => 'WPOptimize',
            'plugin_url' => 'wp-optimize/wp-optimize.php',
            'author' => 'TeamUpdraft, DavidAnderson',
            'description' => 'WP-Optimize rend votre site rapide et efficace. Il nettoie la base de données, compresse les images et met les pages en cache. Les sites rapides attirent plus de trafic et d\'utilisateurs.'
        ],
    ];

    df_plugin_loop_render($plugins_recommandes);
}

function optionnal_plugins() {
    $plugins_optionnel = [
        'better-search-replace' => [
            'name' => 'Better Search Replace',
            'plugin_url' => 'better-search-replace/better-search-replace.php',
            'author' => 'WP Engine',
            'description' => 'Une petite extension pour effectuer des rechercher/remplacer dans votre base de données WordPress.',
        ],

        'password-protected' => [
            'name' => 'Password Protected',
            'plugin_url' => 'password-protected/password-protected.php',
            'author' => 'Password Protected',
            'description' => 'Un moyen très simple pour protéger rapidement votre site WordPress avec un seul mot de passe. Nota bene : cette extension ne restreint pas l’accès aux fichiers et images téléversées et ne fonctionne pas sur WP Engine ou avec certains systèmes de cache.'
        ],
        'contact-form-7' => [
            'name' => 'Popup Maker',
            'plugin_url' => 'contact-form-7/wp-contact-form-7.php',
            'author' => 'Popup Maker ',
            'description' => 'Créez et stylisez facilement des fenêtres modales avec n’importe quel contenu. Éditeur de thèmes pour styliser rapidement vos fenêtres modales. Ajoutez des formulaires, des boîtes de médias sociaux, des vidéos et plus encore.'
        ],
        'ameliabooking' => [
            'name' => 'Amelia',
            'plugin_url' => 'ameliabooking/ameliabooking.php',
            'author' => 'TMS',
            'description' => 'Amelia est un outil de réservation automatisé simple mais puissant, qui fonctionne 24 heures sur 24, 7 jours sur 7, pour permettre à vos clients de prendre des rendez-vous et de réserver des événements même pendant que vous dormez !'
        ],
        'woocommerce' => [
            'name' => 'WooCommerce',
            'plugin_url' => 'woocommerce/woocommerce.php',
            'author' => 'Automattic',
            'description' => 'Tout ce dont vous avez besoin pour créer une boutique en ligne en quelques jours et la faire prospérer des années. Woo vous accompagne, de votre toute première vente au développement colossal de votre chiffre d’affaires.'
        ],
    ];

    df_plugin_loop_render($plugins_optionnel);
}

function df_plugin_loop_render($plugins){
    ?>

    <div class="df_plugins-grid" id="plugins-container">
        <?php foreach($plugins as $slug => $plugin) : ?>
            <div class="df_plugin-card" data-plugin="<?php echo $slug ?>">
                <div class="df_plugin-header-card">
                    <div class="df_plugin-icon">
                        <?php echo $plugin["name"][0] ?>
                    </div>
                    <div class="df_plugin-info">
                        <h3><?php echo $plugin["name"] ?></h3>
                    </div>
                </div>

                <div class="df_plugin-description">
                    <?php echo $plugin["description"] ?? '' ?>
                </div>

                <div class="df_plugin-meta">
                    <span>Par <?php echo $plugin["author"] ?? '' ?></span>
                    <span class="df_status-badge df_status-<?php echo is_plugin_active($plugin["plugin_url"]) ? "active": "inactive" ?>">
                        <?php echo is_plugin_active($plugin["plugin_url"]) ? "Actif": "Inactif" ?>
                    </span>

                </div>

                <div class="df_plugin-actions">
                    <?php $url = admin_url("plugin-install.php?s={$slug}&tab=search&type=term"); ?>
                    <?php
                    if(!file_exists( WP_PLUGIN_DIR.'/'.$plugin["plugin_url"] )){//!is_plugin_active($plugin["plugin_url"])
                        if(!empty($plugin["custom_dl_url"])){
                            echo  '<a href="custom_dl_url" class="df_btn df_btn-primary" type="button" target="_blank" title="More info about ' . $plugin["name"] . '">Installer</a>';
                        }else{
                            echo '<a href="' . esc_url( network_admin_url('plugin-install.php?tab=plugin-information&plugin=' . $slug . '&TB_iframe=true&width=600&height=550' ) ) . '" class="thickbox df_btn df_btn-primary" type="button" title="More info about ' . $plugin["name"] . '">Installer</a>';
                        }
                    }else{
                        echo "<span class='df_btn df_btn-disable'> Installé !</span>";
                        if(!is_plugin_active($plugin["plugin_url"])){

                            $nonce = wp_create_nonce('activate-plugin_' . $slug);
                            echo  '<button  class="df_btn df_btn-primary activate-plugin-btn" type="button" data-pluginpath="'.$plugin["plugin_url"].'" data-slug="'.$slug.'" data-nonce="'.$nonce.'" title="Activer : ' . $plugin["name"] . '">Activer</button>';
                        }
                    }

                    ?>


                </div>
            </div>
            <script>
                [...document.querySelectorAll('.activate-plugin-btn')].forEach(e =>{
                    e.addEventListener('click', function (el) {
                        console.log(el.target.dataset.nonce);
                        fetch("<?php echo admin_url( 'admin-ajax.php' ) ?>", {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({
                                action: 'df_activate_plugin',
                                slug: el.target.dataset.slug,
                                pluginPath: el.target.dataset.pluginpath,
                                nonce: el.target.dataset.nonce
                            })
                        })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    alert('Plugin activé avec succès !');
                                    location.reload(); // Optionnel
                                } else {
                                    alert('Erreur : ' + data.data);
                                }
                            });
                    });
                })
            </script>
        <?php endforeach;?>
    </div>
<?php
}

add_action('admin_menu', 'df_plugin_add_thickbox');
function df_plugin_add_thickbox() {
    add_thickbox();
}

add_action('wp_ajax_df_activate_plugin', function () {
    if (!current_user_can('activate_plugins')) {
        wp_send_json_error('Permission refusée');
    }

    // Vérifie le nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'activate-plugin_'.$_POST["slug"])) {
        wp_send_json_error('Nonce invalide ou expiré');
    }

    if (empty($_POST['pluginPath'])) {
        wp_send_json_error('Aucun plugin spécifié');
    }

    $plugin = sanitize_text_field($_POST['pluginPath']);

    // Inclut les fonctions nécessaires si non chargées
    if (!function_exists('activate_plugin')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $result = activate_plugin($plugin);

    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }

    wp_send_json_success('Plugin activé');
});
?>