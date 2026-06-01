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
        ];

        echo '<table class="widefat striped" style="max-width:680px;margin-bottom:12px">';
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
            'show_rating' => 'true',
            'count'       => 5,
        ], $atts, 'df-google-reviews');

        $data = $this->get_reviews();

        if (!$data || empty($data['reviews'])) {
            // Indice discret pour les admins, rien pour les visiteurs.
            return current_user_can('manage_options')
                ? '<!-- df-google-reviews : configuration manquante ou aucun avis disponible -->'
                : '';
        }

        $this->enqueue_assets();

        $show_rating = filter_var($atts['show_rating'], FILTER_VALIDATE_BOOLEAN);
        $count       = max(1, min(5, (int) $atts['count']));
        $reviews     = array_slice($data['reviews'], 0, $count);

        ob_start();
        ?>
        <div class="dfs-google-reviews">
            <?php if ($show_rating) : ?>
                <div class="dfs-google-reviews__header">
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
                </div>
            <?php endif; ?>

            <div class="dfs-google-reviews__carousel swiper dfs-reviews-swiper">
                <div class="swiper-wrapper">
                    <?php foreach ($reviews as $review) : ?>
                        <div class="swiper-slide dfs-review">
                            <?php if (!empty($review['profile_photo_url'])) : ?>
                                <img class="dfs-review__avatar" src="<?php echo esc_url($review['profile_photo_url']); ?>" alt="" loading="lazy" width="48" height="48" />
                            <?php endif; ?>
                            <span class="dfs-review__author"><?php echo esc_html($review['author_name']); ?></span>
                            <?php echo $this->render_stars((float) $review['rating']); ?>
                            <?php if (!empty($review['relative_time'])) : ?>
                                <span class="dfs-review__date"><?php echo esc_html($review['relative_time']); ?></span>
                            <?php endif; ?>
                            <div class="dfs-review__text"><?php echo nl2br(esc_html($review['text'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
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
     * @return array{rating: float, total: int, reviews: array<int, array<string, mixed>>}|null
     */
    private function get_reviews(): ?array
    {
        $api_key  = trim((string) get_option(self::OPTION_API_KEY, ''));
        $place_id = trim((string) get_option(self::OPTION_PLACE_ID, ''));

        if ($api_key === '' || $place_id === '') {
            return null;
        }

        $hash      = md5($place_id);
        $cache_key = 'dfs_gmb_reviews_' . $hash;

        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        // Évite de marteler l'API juste après une erreur.
        if (get_transient('dfs_gmb_reviews_fail_' . $hash)) {
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
            set_transient('dfs_gmb_reviews_fail_' . $hash, 1, self::FAIL_TTL);
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($body) || ($body['status'] ?? '') !== 'OK' || empty($body['result'])) {
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
            'rating'  => (float) ($result['rating'] ?? 0),
            'total'   => (int) ($result['user_ratings_total'] ?? 0),
            'reviews' => $reviews,
        ];

        set_transient($cache_key, $data, self::CACHE_TTL);

        return $data;
    }
}
