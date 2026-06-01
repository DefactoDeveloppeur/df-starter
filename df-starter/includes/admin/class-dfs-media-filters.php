<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ajoute des filtres « taille en pixels », « poids » et « orientation » dans la
 * liste des médias (Médias > mode liste et mode grille).
 *
 * Les dimensions et le poids sont stockés sérialisés dans _wp_attachment_metadata
 * et ne sont donc pas interrogeables directement. On indexe ces valeurs dans des
 * meta dédiées (_dfs_pixel_max, _dfs_filesize, _dfs_orientation) à l'upload, plus
 * un backfill par lots pour les médias existants, ce qui permet de filtrer via
 * meta_query.
 */
class DFS_Media_Filters
{
    /** Plus grande dimension en pixels (largeur ou hauteur). */
    const PIXEL_MIN = 800;   // En dessous : petit
    const PIXEL_MAX = 1800;  // Au dessus : grand

    /** Poids du fichier en octets. */
    const WEIGHT_MIN = 512000;   // 500 Ko — en dessous : léger
    const WEIGHT_MAX = 2097152;  // 2 Mo   — au dessus : lourd

    /** Taille des lots traités à chaque chargement pour les médias existants. */
    const BACKFILL_BATCH = 75;

    /**
     * Version du schéma d'indexation. À incrémenter quand on ajoute une nouvelle
     * meta calculée : le backfill repasse alors une fois sur les médias déjà
     * indexés. (2 = ajout de _dfs_orientation.)
     */
    const INDEX_VERSION = 2;

    public function register(): void
    {
        add_filter('wp_generate_attachment_metadata', [$this, 'index_on_generate'], 10, 2);
        add_action('load-upload.php', [$this, 'maybe_backfill']);

        // Mode liste (upload.php?mode=list)
        add_action('restrict_manage_posts', [$this, 'render_filters']);
        add_action('pre_get_posts', [$this, 'apply_filters']);

        // Mode grille (upload.php?mode=grid + médiathèque JS)
        add_action('admin_enqueue_scripts', [$this, 'enqueue_grid_assets']);
        add_filter('ajax_query_attachments_args', [$this, 'apply_grid_filters']);
    }

    /**
     * Indexe un média lors de la génération de ses métadonnées (upload ou
     * régénération des miniatures).
     */
    public function index_on_generate(array $metadata, int $attachment_id): array
    {
        $this->index_attachment($attachment_id, $metadata);
        return $metadata;
    }

    /**
     * Calcule et stocke les meta d'indexation pour un média.
     */
    public function index_attachment(int $attachment_id, ?array $metadata = null): void
    {
        if ($metadata === null) {
            $metadata = wp_get_attachment_metadata($attachment_id);
        }
        $metadata = is_array($metadata) ? $metadata : [];

        $width  = isset($metadata['width']) ? (int) $metadata['width'] : 0;
        $height = isset($metadata['height']) ? (int) $metadata['height'] : 0;
        $pixel_max = max($width, $height);

        if ($pixel_max > 0) {
            update_post_meta($attachment_id, '_dfs_pixel_max', $pixel_max);
        } else {
            delete_post_meta($attachment_id, '_dfs_pixel_max');
        }

        $filesize = isset($metadata['filesize']) ? (int) $metadata['filesize'] : 0;
        if ($filesize <= 0) {
            $file = get_attached_file($attachment_id);
            if ($file && file_exists($file)) {
                $filesize = (int) wp_filesize($file);
            }
        }

        if ($filesize > 0) {
            update_post_meta($attachment_id, '_dfs_filesize', $filesize);
        } else {
            delete_post_meta($attachment_id, '_dfs_filesize');
        }

        if ($width > 0 && $height > 0) {
            $orientation = $width === $height ? 'square' : ($width > $height ? 'landscape' : 'portrait');
            update_post_meta($attachment_id, '_dfs_orientation', $orientation);
        } else {
            delete_post_meta($attachment_id, '_dfs_orientation');
        }

        // Marqueur (= version du schéma) posé sur tous les médias traités, même
        // non-images, pour que le backfill sache lesquels sont à jour.
        update_post_meta($attachment_id, '_dfs_media_indexed', self::INDEX_VERSION);
    }

    /**
     * Indexe par lots les médias existants, le temps que tout soit couvert.
     */
    public function maybe_backfill(): void
    {
        if ((int) get_option('dfs_media_index_version') >= self::INDEX_VERSION) {
            return;
        }

        // Médias jamais indexés, ou indexés avec un schéma plus ancien.
        $query = new WP_Query([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => self::BACKFILL_BATCH,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [
                'relation' => 'OR',
                ['key' => '_dfs_media_indexed', 'compare' => 'NOT EXISTS'],
                ['key' => '_dfs_media_indexed', 'value' => self::INDEX_VERSION, 'compare' => '<', 'type' => 'NUMERIC'],
            ],
        ]);

        if (empty($query->posts)) {
            update_option('dfs_media_index_version', self::INDEX_VERSION);
            return;
        }

        foreach ($query->posts as $attachment_id) {
            $this->index_attachment((int) $attachment_id);
        }
    }

    /**
     * Affiche les deux menus déroulants dans la barre de filtres de la liste.
     */
    public function render_filters(string $post_type): void
    {
        if ($post_type !== 'attachment') {
            return;
        }

        $pixel       = isset($_GET['dfs_pixel']) ? sanitize_key($_GET['dfs_pixel']) : '';
        $weight      = isset($_GET['dfs_weight']) ? sanitize_key($_GET['dfs_weight']) : '';
        $orientation = isset($_GET['dfs_orientation']) ? sanitize_key($_GET['dfs_orientation']) : '';

        $this->render_select('dfs_pixel', __('Filtrer par taille', 'df-starter'), [
            ''      => __('Toutes les tailles', 'df-starter'),
            'grand' => __('Grand (> 1800px)', 'df-starter'),
            'moyen' => __('Moyen (800–1800px)', 'df-starter'),
            'petit' => __('Petit (< 800px)', 'df-starter'),
        ], $pixel);

        $this->render_select('dfs_weight', __('Filtrer par poids', 'df-starter'), [
            ''      => __('Tous les poids', 'df-starter'),
            'leger' => __('Léger (< 500 Ko)', 'df-starter'),
            'moyen' => __('Moyen (500 Ko – 2 Mo)', 'df-starter'),
            'lourd' => __('Lourd (> 2 Mo)', 'df-starter'),
        ], $weight);

        $this->render_select('dfs_orientation', __('Filtrer par orientation', 'df-starter'), [
            ''          => __('Toutes les orientations', 'df-starter'),
            'landscape' => __('Paysage', 'df-starter'),
            'portrait'  => __('Portrait', 'df-starter'),
            'square'    => __('Carré', 'df-starter'),
        ], $orientation);
    }

    /**
     * Affiche un menu déroulant de filtre avec son label accessible.
     *
     * @param array<string, string> $options
     */
    private function render_select(string $name, string $label, array $options, string $selected): void
    {
        echo '<label class="screen-reader-text" for="' . esc_attr($name) . '">' . esc_html($label) . '</label>';
        echo '<select name="' . esc_attr($name) . '" id="' . esc_attr($name) . '">';
        foreach ($options as $value => $text) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($value),
                selected($selected, $value, false),
                esc_html($text)
            );
        }
        echo '</select>';
    }

    /**
     * Applique les filtres sélectionnés à la requête de la liste des médias.
     */
    public function apply_filters(WP_Query $query): void
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        global $pagenow;
        if ($pagenow !== 'upload.php') {
            return;
        }

        $meta_query = $query->get('meta_query');
        $meta_query = is_array($meta_query) ? $meta_query : [];

        $pixel       = isset($_GET['dfs_pixel']) ? sanitize_key($_GET['dfs_pixel']) : '';
        $weight      = isset($_GET['dfs_weight']) ? sanitize_key($_GET['dfs_weight']) : '';
        $orientation = isset($_GET['dfs_orientation']) ? sanitize_key($_GET['dfs_orientation']) : '';

        if ($clause = $this->pixel_clause($pixel)) {
            $meta_query[] = $clause;
        }

        if ($clause = $this->weight_clause($weight)) {
            $meta_query[] = $clause;
        }

        if ($clause = $this->orientation_clause($orientation)) {
            $meta_query[] = $clause;
        }

        if (!empty($meta_query)) {
            $query->set('meta_query', $meta_query);
        }
    }

    /**
     * Charge le script qui injecte les menus déroulants dans la barre d'outils
     * de la médiathèque en mode grille.
     */
    public function enqueue_grid_assets(string $hook): void
    {
        if ($hook !== 'upload.php') {
            return;
        }

        wp_enqueue_script(
            'dfs-media-filters',
            DFS_URL . 'assets/js/media-filters.js',
            ['media-views'],
            DFS_Assets::asset_version('assets/js/media-filters.js'),
            true
        );

        wp_localize_script('dfs-media-filters', 'dfsMediaFilters', [
            'pixel' => [
                'label' => __('Filtrer par taille', 'df-starter'),
                'all'   => __('Toutes les tailles', 'df-starter'),
                'grand' => __('Grand (> 1800px)', 'df-starter'),
                'moyen' => __('Moyen (800–1800px)', 'df-starter'),
                'petit' => __('Petit (< 800px)', 'df-starter'),
            ],
            'weight' => [
                'label' => __('Filtrer par poids', 'df-starter'),
                'all'   => __('Tous les poids', 'df-starter'),
                'leger' => __('Léger (< 500 Ko)', 'df-starter'),
                'moyen' => __('Moyen (500 Ko – 2 Mo)', 'df-starter'),
                'lourd' => __('Lourd (> 2 Mo)', 'df-starter'),
            ],
            'orientation' => [
                'label'     => __('Filtrer par orientation', 'df-starter'),
                'all'       => __('Toutes les orientations', 'df-starter'),
                'landscape' => __('Paysage', 'df-starter'),
                'portrait'  => __('Portrait', 'df-starter'),
                'square'    => __('Carré', 'df-starter'),
            ],
        ]);
    }

    /**
     * Applique les filtres envoyés par la grille (requête AJAX query-attachments).
     *
     * Les clés personnalisées sont retirées du tableau `$query` par le cœur de
     * WordPress (array_intersect_key), mais restent disponibles dans la requête
     * brute, d'où la lecture directe dans $_REQUEST['query'].
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function apply_grid_filters(array $query): array
    {
        $request = (isset($_REQUEST['query']) && is_array($_REQUEST['query'])) ? $_REQUEST['query'] : [];

        $pixel       = isset($request['dfs_pixel']) ? sanitize_key($request['dfs_pixel']) : '';
        $weight      = isset($request['dfs_weight']) ? sanitize_key($request['dfs_weight']) : '';
        $orientation = isset($request['dfs_orientation']) ? sanitize_key($request['dfs_orientation']) : '';

        $meta_query = (isset($query['meta_query']) && is_array($query['meta_query'])) ? $query['meta_query'] : [];

        if ($clause = $this->pixel_clause($pixel)) {
            $meta_query[] = $clause;
        }

        if ($clause = $this->weight_clause($weight)) {
            $meta_query[] = $clause;
        }

        if ($clause = $this->orientation_clause($orientation)) {
            $meta_query[] = $clause;
        }

        if (!empty($meta_query)) {
            $query['meta_query'] = $meta_query;
        }

        return $query;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pixel_clause(string $value): ?array
    {
        switch ($value) {
            case 'grand':
                return ['key' => '_dfs_pixel_max', 'value' => self::PIXEL_MAX, 'compare' => '>', 'type' => 'NUMERIC'];
            case 'moyen':
                return ['key' => '_dfs_pixel_max', 'value' => [self::PIXEL_MIN, self::PIXEL_MAX], 'compare' => 'BETWEEN', 'type' => 'NUMERIC'];
            case 'petit':
                return ['key' => '_dfs_pixel_max', 'value' => self::PIXEL_MIN, 'compare' => '<', 'type' => 'NUMERIC'];
        }
        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function weight_clause(string $value): ?array
    {
        switch ($value) {
            case 'leger':
                return ['key' => '_dfs_filesize', 'value' => self::WEIGHT_MIN, 'compare' => '<', 'type' => 'NUMERIC'];
            case 'moyen':
                return ['key' => '_dfs_filesize', 'value' => [self::WEIGHT_MIN, self::WEIGHT_MAX], 'compare' => 'BETWEEN', 'type' => 'NUMERIC'];
            case 'lourd':
                return ['key' => '_dfs_filesize', 'value' => self::WEIGHT_MAX, 'compare' => '>', 'type' => 'NUMERIC'];
        }
        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function orientation_clause(string $value): ?array
    {
        if (in_array($value, ['landscape', 'portrait', 'square'], true)) {
            return ['key' => '_dfs_orientation', 'value' => $value, 'compare' => '='];
        }
        return null;
    }
}
