/**
 * Ajoute les filtres « taille en pixels » et « poids » dans la barre d'outils
 * de la médiathèque en mode grille (wp.media).
 *
 * Chaque filtre pose une prop (dfs_pixel / dfs_weight) sur la requête ; cette
 * valeur est envoyée dans la requête AJAX query-attachments et lue côté serveur
 * par DFS_Media_Filters::apply_grid_filters().
 */
(function () {
    'use strict';

    if (typeof wp === 'undefined' || !wp.media || !wp.media.view || !wp.media.view.AttachmentFilters) {
        return;
    }

    var media = wp.media;
    var data = window.dfsMediaFilters || {};
    var pixelL10n = data.pixel || {};
    var weightL10n = data.weight || {};
    var orientationL10n = data.orientation || {};

    // Filtre par taille en pixels (plus grande dimension).
    var PixelFilter = media.view.AttachmentFilters.extend({
        id: 'dfs-pixel-filter',
        createFilters: function () {
            this.filters = {
                all:   { text: pixelL10n.all || 'Toutes les tailles', props: { dfs_pixel: '' }, priority: 10 },
                grand: { text: pixelL10n.grand || 'Grand',            props: { dfs_pixel: 'grand' }, priority: 20 },
                moyen: { text: pixelL10n.moyen || 'Moyen',            props: { dfs_pixel: 'moyen' }, priority: 30 },
                petit: { text: pixelL10n.petit || 'Petit',            props: { dfs_pixel: 'petit' }, priority: 40 }
            };
        }
    });

    // Filtre par poids (taille du fichier).
    var WeightFilter = media.view.AttachmentFilters.extend({
        id: 'dfs-weight-filter',
        createFilters: function () {
            this.filters = {
                all:   { text: weightL10n.all || 'Tous les poids', props: { dfs_weight: '' }, priority: 10 },
                leger: { text: weightL10n.leger || 'Léger',        props: { dfs_weight: 'leger' }, priority: 20 },
                moyen: { text: weightL10n.moyen || 'Moyen',        props: { dfs_weight: 'moyen' }, priority: 30 },
                lourd: { text: weightL10n.lourd || 'Lourd',        props: { dfs_weight: 'lourd' }, priority: 40 }
            };
        }
    });

    // Filtre par orientation.
    var OrientationFilter = media.view.AttachmentFilters.extend({
        id: 'dfs-orientation-filter',
        createFilters: function () {
            this.filters = {
                all:       { text: orientationL10n.all || 'Toutes les orientations', props: { dfs_orientation: '' }, priority: 10 },
                landscape: { text: orientationL10n.landscape || 'Paysage',           props: { dfs_orientation: 'landscape' }, priority: 20 },
                portrait:  { text: orientationL10n.portrait || 'Portrait',           props: { dfs_orientation: 'portrait' }, priority: 30 },
                square:    { text: orientationL10n.square || 'Carré',                props: { dfs_orientation: 'square' }, priority: 40 }
            };
        }
    });

    var AttachmentsBrowser = media.view.AttachmentsBrowser;

    media.view.AttachmentsBrowser = AttachmentsBrowser.extend({
        createToolbar: function () {
            AttachmentsBrowser.prototype.createToolbar.call(this);

            this.toolbar.set('dfsPixelFilterLabel', new media.view.Label({
                value: pixelL10n.label || 'Filtrer par taille',
                attributes: { 'for': 'dfs-pixel-filter' },
                priority: -74
            }).render());

            this.toolbar.set('dfsPixelFilter', new PixelFilter({
                controller: this.controller,
                model: this.collection.props,
                priority: -74
            }).render());

            this.toolbar.set('dfsWeightFilterLabel', new media.view.Label({
                value: weightL10n.label || 'Filtrer par poids',
                attributes: { 'for': 'dfs-weight-filter' },
                priority: -73
            }).render());

            this.toolbar.set('dfsWeightFilter', new WeightFilter({
                controller: this.controller,
                model: this.collection.props,
                priority: -73
            }).render());

            this.toolbar.set('dfsOrientationFilterLabel', new media.view.Label({
                value: orientationL10n.label || 'Filtrer par orientation',
                attributes: { 'for': 'dfs-orientation-filter' },
                priority: -72
            }).render());

            this.toolbar.set('dfsOrientationFilter', new OrientationFilter({
                controller: this.controller,
                model: this.collection.props,
                priority: -72
            }).render());
        }
    });
})();
