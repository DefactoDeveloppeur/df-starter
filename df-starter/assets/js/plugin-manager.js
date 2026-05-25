(function () {
    'use strict';

    const cfg = window.dfsPluginManager || {};

    document.addEventListener('click', function (event) {
        const btn = event.target.closest('.activate-plugin-btn');
        if (!btn) return;

        const body = new URLSearchParams({
            action:     'dfs_activate_plugin',
            slug:       btn.dataset.slug,
            pluginPath: btn.dataset.pluginpath,
            nonce:      btn.dataset.nonce,
        });

        fetch(cfg.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body,
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Erreur : ' + (data.data || ''));
                }
            });
    });
})();
