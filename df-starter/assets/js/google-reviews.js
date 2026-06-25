/**
 * Initialise le(s) carousel(s) d'avis Google.
 *
 * Swiper.js n'est chargé depuis le CDN que s'il n'est pas déjà présent sur la
 * page (window.Swiper), afin de ne jamais le charger en double quand le thème
 * ou un autre plugin l'utilise déjà.
 */
(function () {
    'use strict';

    var cfg = window.dfsGoogleReviews || {};
    var swiperCfg = cfg.swiper || {};

    function buildOptions(el) {
        // Base : options globales localisées, puis options propres au carousel.
        var options = Object.assign({}, swiperCfg.options || {});

        if (el.dataset.swiperOptions) {
            try {
                Object.assign(options, JSON.parse(el.dataset.swiperOptions));
            } catch (e) {
                /* JSON invalide : on garde les défauts. */
            }
        }

        // Flèches/points : ces éléments sont des frères de `el`, placés dans le
        // container (.dfs-google-reviews__carousel-container) pour le position:
        // relative. On les résout donc depuis ce container, pas depuis `el`.
        var scope = el.parentNode || el;
        if (options.navigation === true) {
            options.navigation = {
                nextEl: scope.querySelector('.swiper-button-next'),
                prevEl: scope.querySelector('.swiper-button-prev')
            };
        }
        if (options.pagination === true) {
            options.pagination = {
                el: scope.querySelector('.swiper-pagination'),
                clickable: true
            };
        }

        return options;
    }

    function initAll() {
        if (typeof window.Swiper === 'undefined') {
            return;
        }
        var carousels = document.querySelectorAll('.dfs-reviews-swiper');
        Array.prototype.forEach.call(carousels, function (el) {
            if (el.dataset.dfsInit) {
                return;
            }
            el.dataset.dfsInit = '1';
            new window.Swiper(el, buildOptions(el));
        });
    }

    function loadCss(href) {
        if (!href || document.querySelector('link[href="' + href + '"]')) {
            return;
        }
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        document.head.appendChild(link);
    }

    function ensureSwiper(callback) {
        // Swiper déjà chargé (thème / autre plugin) : on le réutilise tel quel.
        if (typeof window.Swiper !== 'undefined') {
            callback();
            return;
        }

        if (!swiperCfg.js) {
            return;
        }

        loadCss(swiperCfg.css);

        var existing = document.querySelector('script[data-dfs-swiper]');
        if (existing) {
            existing.addEventListener('load', callback);
            return;
        }

        var script = document.createElement('script');
        script.src = swiperCfg.js;
        script.setAttribute('data-dfs-swiper', '1');
        script.onload = callback;
        document.head.appendChild(script);
    }

    function boot() {
        ensureSwiper(initAll);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
