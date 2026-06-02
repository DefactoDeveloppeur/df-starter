<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Récupère les avis d'une fiche Google (Google My Business) via la Google Places
 * API et les expose dans un shortcode [df-google-reviews].
 *
 * Affichage : un en-tête centré (nombre d'avis + note /5 en étoiles dynamiques,
 * masquable) suivi d'un carousel Swiper.js volontairement non stylé (markup à
 * classes uniquement) pour être habillé par le thème.
 *
 * La Places API ne renvoyant que 5 avis au maximum, la réponse est mise en cache
 * 12h (transient) pour limiter les appels facturés.
 */
class DFS_Google_Reviews
{
    const OPTION_API_KEY  = 'dfs_gmb_api_key';
    const OPTION_PLACE_ID = 'dfs_gmb_place_id';

    const CACHE_TTL = 12 * HOUR_IN_SECONDS;
    const FAIL_TTL  = 15 * MINUTE_IN_SECONDS;

    /** Empêche de localiser/charger les assets plusieurs fois sur une même page. */
    private $assets_done = false;

    /** Dernière raison d'échec de récupération (pour le diagnostic admin). */
    private $last_error = '';

    public function register(): void
    {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('update_option_' . self::OPTION_PLACE_ID, [$this, 'clear_cache'], 10, 2);
        add_action('update_option_' . self::OPTION_API_KEY, [$this, 'clear_cache'], 10, 2);

        add_shortcode('df-google-reviews', [$this, 'render_shortcode']);
    }

    /* ---------------------------------------------------------------------
     * Réglages (greffés sur la page de configuration du plugin)
     * ------------------------------------------------------------------- */

    public function register_settings(): void
    {
        register_setting(DFS_Settings_Page::OPTION_GROUP, self::OPTION_API_KEY, [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);
        register_setting(DFS_Settings_Page::OPTION_GROUP, self::OPTION_PLACE_ID, [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);

        add_settings_section(
            'dfs_google_reviews',
            __('Avis Google (Google My Business)', 'df-starter'),
            [$this, 'render_section_intro'],
            DFS_Settings_Page::OPTION_GROUP
        );

        add_settings_field(
            self::OPTION_API_KEY,
            __('Clé API Google Places', 'df-starter'),
            [$this, 'render_field_api_key'],
            DFS_Settings_Page::OPTION_GROUP,
            'dfs_google_reviews'
        );
        add_settings_field(
            self::OPTION_PLACE_ID,
            __('Place ID', 'df-starter'),
            [$this, 'render_field_place_id'],
            DFS_Settings_Page::OPTION_GROUP,
            'dfs_google_reviews'
        );
    }

    public function render_section_intro(): void
    {
        echo '<p>' . esc_html__('Renseignez une clé API Google Cloud (API « Places » activée) et le Place ID de la fiche. Affichez ensuite les avis avec le shortcode :', 'df-starter') . '</p>';

        $examples = [
            '[df-google-reviews]'                     => __('en-tête (note + nombre d\'avis) + carousel des 5 avis', 'df-starter'),
            '[df-google-reviews show_rating="false"]' => __('carousel seul, sans l\'en-tête', 'df-starter'),
            '[df-google-reviews count="3"]'           => __('limite le carousel à 3 avis (max 5, limite de l\'API)', 'df-starter'),
            '[df-google-reviews navigation="true" pagination="true"]' => __('affiche les flèches et les points de navigation', 'df-starter'),
            '[df-google-reviews loop="true" autoplay="4000"]'         => __('défilement infini + lecture auto toutes les 4 s', 'df-starter'),
            '[df-google-reviews slides_per_view="3"]'                 => __('1 slide sur mobile/tablette, 3 sur ordinateur', 'df-starter'),
            '[df-google-reviews link="true" g_icon="big"]'                         => __('ajoute un lien vers la page d\'avis Google de la fiche + L\'ajout de l\'icône Google', 'df-starter'),
        ];

        echo '<table class="widefat striped" style="max-width:780px;margin-bottom:12px">';
        echo '<thead><tr>'
            . '<th>' . esc_html__('Shortcode', 'df-starter') . '</th>'
            . '<th>' . esc_html__('Résultat', 'df-starter') . '</th>'
            . '</tr></thead><tbody>';
        foreach ($examples as $shortcode => $description) {
            echo '<tr>'
                . '<td><code>' . esc_html($shortcode) . '</code></td>'
                . '<td>' . esc_html($description) . '</td>'
                . '</tr>';
        }
        echo '</tbody></table>';

        echo '<p class="description" style="max-width:780px">'
            . esc_html__('Attributs disponibles : show_rating, count (1-5), slides_per_view (nombre sur ordinateur ; mobile/tablette = 1), space_between (px), loop, navigation, pagination, autoplay (true ou délai en ms), link (true pour afficher le lien vers Google), link_text (texte du lien), g_icon ("big" ou "little" pour l\'icone Google). Les attributs se combinent.', 'df-starter')
            . '</p>';

        echo '<p><a href="https://developers.google.com/maps/documentation/places/web-service/place-id" target="_blank" rel="noopener">'
            . esc_html__('Trouver le Place ID de la fiche', 'df-starter') . '</a></p>';
    }

    public function render_field_api_key(): void
    {
        printf(
            '<input type="password" class="regular-text" name="%1$s" value="%2$s" autocomplete="off" spellcheck="false" />',
            esc_attr(self::OPTION_API_KEY),
            esc_attr(get_option(self::OPTION_API_KEY, ''))
        );
        echo '<p class="description">' . esc_html__('Stockée masquée. Pour la remplacer, saisissez une nouvelle clé.', 'df-starter') . '</p>';
    }

    public function render_field_place_id(): void
    {
        printf(
            '<input type="text" class="regular-text" name="%s" value="%s" placeholder="ChIJ..." />',
            esc_attr(self::OPTION_PLACE_ID),
            esc_attr(get_option(self::OPTION_PLACE_ID, ''))
        );
    }

    /**
     * Vide le cache lorsqu'on change la clé ou le Place ID.
     */
    public function clear_cache($old_value = '', $value = ''): void
    {
        $candidates = array_unique(array_filter([
            (string) $old_value,
            (string) $value,
            (string) get_option(self::OPTION_PLACE_ID, ''),
        ]));

        foreach ($candidates as $place_id) {
            $hash = md5($place_id);
            delete_transient('dfs_gmb_reviews_' . $hash);
            delete_transient('dfs_gmb_reviews_fail_' . $hash);
        }
    }

    /* ---------------------------------------------------------------------
     * Shortcode
     * ------------------------------------------------------------------- */

    /**
     * @param array<string, mixed>|string $atts
     */
    public function render_shortcode($atts): string
    {
        $atts = shortcode_atts([
            'show_rating'     => 'true',
            'count'           => 5,
            'slides_per_view' => 1,      // sur ordinateur ; mobile/tablette = 1
            'space_between'   => 0,
            'loop'            => 'false',
            'navigation'      => 'false',
            'pagination'      => 'false',
            'autoplay'        => 'false',
            'g_icon'          => 'none',
            'link'            => 'true',
            'link_text'       => '',
        ], $atts, 'df-google-reviews');

        $data = $this->get_reviews();

        if (!$data || empty($data['reviews'])) {
            // Rien pour les visiteurs ; diagnostic détaillé pour les admins.
            if (!current_user_can('manage_options')) {
                return '';
            }
            return '<!-- df-google-reviews : ' . esc_html($this->last_error ?: 'aucun avis disponible') . ' -->';
        }

        $this->enqueue_assets();

        $show_rating = filter_var($atts['show_rating'], FILTER_VALIDATE_BOOLEAN);
        $count       = max(1, min(5, (int) $atts['count']));
        $reviews     = array_slice($data['reviews'], 0, $count);

        $navigation = filter_var($atts['navigation'], FILTER_VALIDATE_BOOLEAN);
        $pagination = filter_var($atts['pagination'], FILTER_VALIDATE_BOOLEAN);
        $g_icon     = in_array($atts['g_icon'], ["big", "little"]) ? $atts['g_icon'] : "none";
        $options    = $this->build_swiper_options($atts, $navigation, $pagination);

        // Lien vers la page d'avis de la fiche Google My Business.
        // Le Place ID est lu depuis l'option (toujours disponible), pas depuis le
        // cache des avis qui pourrait dater d'avant l'ajout de cette donnée.
        $place_id    = trim((string) get_option(self::OPTION_PLACE_ID, ''));
        $show_link   = filter_var($atts['link'], FILTER_VALIDATE_BOOLEAN) && $place_id !== '';
        $link_text   = $atts['link_text'] !== '' ? $atts['link_text'] : __('Voir tous nos avis sur Google', 'df-starter');
        $reviews_url = 'https://search.google.com/local/reviews?placeid=' . rawurlencode($place_id);

        ob_start();
        ?>
        <div class="dfs-google-reviews">
            <?php if ($show_rating) : ?>
                <div class="dfs-google-reviews__header">
                    <?php if ($show_link) : ?>
                    <a class="dfs-google-reviews__link" href="<?php echo esc_url($reviews_url); ?>" target="_blank" rel="noopener noreferrer" style="display: inline-block;">
                    <?php endif; ?>
                        <?php if($g_icon): ?>
                        <div class="dfs-google-reviews__g_icon">
                            <?php if($g_icon == "big"):?>
                                <img src="<?php echo  DFS_URL . 'assets/google-brand-color.svg' ?>" width="150px" alt="Google reviews Logo">
                            <?php elseif($g_icon == "little"): ?>
                                <img src="<?php echo  DFS_URL . 'assets/google.webp' ?>" width="50px" style="border-radius: 100%" alt="Google reviews Logo">
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <span class="dfs-google-reviews__count"><?php
                            /* translators: %s = nombre d'avis */
                            printf(
                                esc_html(_n('%s avis', '%s avis', (int) $data['total'], 'df-starter')),
                                esc_html(number_format_i18n((int) $data['total']))
                            );
                        ?></span>
                        <span class="dfs-google-reviews__rating">
                            <?php echo $this->render_stars((float) $data['rating']); ?>
                            <span class="dfs-google-reviews__score"><?php echo esc_html(number_format_i18n((float) $data['rating'], 1)); ?>/5</span>
                        </span>
                    <?php if ($show_link) : ?>
                    </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($show_link && !$show_rating) : ?>
                <a class="dfs-google-reviews__link" href="<?php echo esc_url($reviews_url); ?>" target="_blank" rel="noopener noreferrer">
                    <?php echo esc_html($link_text); ?>
                </a>
            <?php endif; ?>

            <div class="dfs-google-reviews__carousel-container" style="position: relative;">
                <div class="dfs-google-reviews__carousel swiper dfs-reviews-swiper" data-swiper-options="<?php echo esc_attr((string) wp_json_encode($options)); ?>">
                    <div class="swiper-wrapper">
                        <?php foreach ($reviews as $review) : ?>
                            <div class="swiper-slide dfs-review">
                                <div class="dfs-review-header">
                                    <?php if (!empty($review['profile_photo_url'])) : ?>
                                        <div class="dfs-review__avatar-wrapper">
                                            <img class="dfs-review__avatar" src="<?php echo esc_url($review['profile_photo_url']); ?>" alt="" loading="lazy" width="48" height="48" />
                                        </div>
                                    <?php endif; ?>
                                    <span class="dfs-review__author"><?php echo esc_html($review['author_name']); ?></span>
                                    <?php echo $this->render_stars((float) $review['rating']); ?>
                                    <?php if (!empty($review['relative_time'])) : ?>
                                        <span class="dfs-review__date"><?php echo esc_html($review['relative_time']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="dfs-review__text"><?php echo nl2br(esc_html($review['text'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>


                </div>
                <?php if ($pagination) : ?>
                    <div class="swiper-pagination"></div>
                <?php endif; ?>
                <?php if ($navigation) : ?>
                    <div class="swiper-button-prev"></div>
                    <div class="swiper-button-next"></div>
                <?php endif; ?>
            </div>

        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Construit les options Swiper à partir des attributs du shortcode.
     *
     * navigation/pagination sont passés en booléens : le JS les remplace par les
     * éléments DOM scopés à chaque carousel (évite les conflits multi-instances).
     *
     * @param array<string, mixed> $atts
     * @return array<string, mixed>
     */
    private function build_swiper_options(array $atts, bool $navigation, bool $pagination): array
    {
        // Mobile + tablette : 1 slide. Desktop (≥1024px) : valeur choisie.
        $desktop_slides = max(1, (int) $atts['slides_per_view']);

        $options = [
            'slidesPerView' => 1,
            'spaceBetween'  => max(0, (int) $atts['space_between']),
            'loop'          => filter_var($atts['loop'], FILTER_VALIDATE_BOOLEAN),
        ];

        if ($desktop_slides > 1) {
            $options['breakpoints'] = [
                1024 => ['slidesPerView' => $desktop_slides],
            ];
        }

        if ($navigation) {
            $options['navigation'] = true;
        }
        if ($pagination) {
            $options['pagination'] = true;
        }

        // autoplay="true" → 3s ; autoplay="4000" → délai en ms.
        $autoplay = strtolower((string) $atts['autoplay']);
        if ($autoplay !== '' && $autoplay !== 'false' && $autoplay !== '0') {
            $delay = is_numeric($autoplay) ? max(500, (int) $autoplay) : 3000;
            $options['autoplay'] = ['delay' => $delay, 'disableOnInteraction' => false];
        }

        return $options;
    }

    /**
     * Étoiles dynamiques : 5 étoiles « vides » avec une surcouche « pleine »
     * tronquée à la largeur correspondant à la note (gère les demi-étoiles).
     */
    private function render_stars(float $rating): string
    {
        $percent = max(0, min(100, ($rating / 5) * 100));
        $stars   = '★★★★★';

        return '<span class="dfs-stars" role="img" aria-label="'
            . esc_attr(sprintf(
                /* translators: %s = note sur 5 */
                __('Note de %s sur 5', 'df-starter'),
                number_format_i18n($rating, 1)
            )) . '">'
            . '<span class="dfs-stars__base" aria-hidden="true">' . $stars . '</span>'
            . '<span class="dfs-stars__fill" aria-hidden="true" style="width:' . esc_attr((string) $percent) . '%">' . $stars . '</span>'
            . '</span>';
    }

    private function enqueue_assets(): void
    {
        if ($this->assets_done) {
            return;
        }
        $this->assets_done = true;

        wp_enqueue_style(
            'dfs-google-reviews',
            DFS_URL . 'assets/css/google-reviews.css',
            [],
            DFS_Assets::asset_version('assets/css/google-reviews.css')
        );

        wp_enqueue_script(
            'dfs-google-reviews',
            DFS_URL . 'assets/js/google-reviews.js',
            [],
            DFS_Assets::asset_version('assets/js/google-reviews.js'),
            true
        );

        wp_localize_script('dfs-google-reviews', 'dfsGoogleReviews', [
            'swiper' => [
                // Chargés depuis le CDN uniquement si Swiper n'est pas déjà présent.
                'js'      => 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js',
                'css'     => 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css',
                'options' => apply_filters('dfs_google_reviews_swiper_options', [
                    'slidesPerView' => 1,
                ]),
            ],
        ]);
    }

    /* ---------------------------------------------------------------------
     * Récupération des avis (Google Places API – Place Details)
     * ------------------------------------------------------------------- */

    /**
     * @return array{rating: float, total: int, place_id: string, reviews: array<int, array<string, mixed>>}|null
     */
    private function get_reviews(): ?array
    {
        $api_key  = trim((string) get_option(self::OPTION_API_KEY, ''));
        $place_id = trim((string) get_option(self::OPTION_PLACE_ID, ''));

        if ($api_key === '' || $place_id === '') {
            $this->last_error = 'configuration manquante (clé API et/ou Place ID)';
            return null;
        }

        $hash      = md5($place_id);
        $cache_key = 'dfs_gmb_reviews_' . $hash;

        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        // Les admins peuvent réessayer immédiatement après correction ; le
        // back-off anti-martèlement ne s'applique qu'aux visiteurs.
        $is_admin = current_user_can('manage_options');
        if (!$is_admin && get_transient('dfs_gmb_reviews_fail_' . $hash)) {
            $this->last_error = 'nouvelle tentative en pause (erreur récente)';
            return null;
        }

        $url = add_query_arg([
            'place_id'                => $place_id,
            'fields'                  => 'rating,user_ratings_total,reviews',
            'language'                => substr(get_locale(), 0, 2),
            'reviews_no_translations' => 'true',
            'key'                     => $api_key,
        ], 'https://maps.googleapis.com/maps/api/place/details/json');

        $response = wp_remote_get($url, ['timeout' => 8]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            $this->last_error = is_wp_error($response)
                ? 'erreur réseau : ' . $response->get_error_message()
                : 'réponse HTTP ' . wp_remote_retrieve_response_code($response);
            set_transient('dfs_gmb_reviews_fail_' . $hash, 1, self::FAIL_TTL);
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($body) || ($body['status'] ?? '') !== 'OK' || empty($body['result'])) {
            $status  = is_array($body) ? (string) ($body['status'] ?? 'réponse illisible') : 'réponse illisible';
            $message = is_array($body) && !empty($body['error_message']) ? ' – ' . $body['error_message'] : '';
            $this->last_error = 'API Google : ' . $status . $message;
            set_transient('dfs_gmb_reviews_fail_' . $hash, 1, self::FAIL_TTL);
            return null;
        }

        $result  = $body['result'];
        $reviews = [];

        foreach (($result['reviews'] ?? []) as $review) {
            $reviews[] = [
                'author_name'       => (string) ($review['author_name'] ?? ''),
                'rating'            => (float) ($review['rating'] ?? 0),
                'text'              => (string) ($review['text'] ?? ''),
                'relative_time'     => (string) ($review['relative_time_description'] ?? ''),
                'time'              => (int) ($review['time'] ?? 0),
                'profile_photo_url' => (string) ($review['profile_photo_url'] ?? ''),
            ];
        }

        $data = [
            'rating'   => (float) ($result['rating'] ?? 0),
            'total'    => (int) ($result['user_ratings_total'] ?? 0),
            'place_id' => $place_id,
            'reviews'  => $reviews,
        ];

        set_transient($cache_key, $data, self::CACHE_TTL);

        return $data;
    }
}
