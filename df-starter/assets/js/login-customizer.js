(function ($) {
    'use strict';

    const cfg = window.dfsLoginCustomizer || {};
    let colorTimeout;
    let fileFrame;

    function ajax(action, payload) {
        const body = new URLSearchParams(Object.assign({
            action: action,
            nonce: cfg.nonce,
        }, payload));

        return fetch(cfg.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body,
        }).then(function (res) { return res.json(); });
    }

    function applyBgColor(color) {
        const panel = document.getElementById('login-panel');
        if (panel) panel.style.backgroundColor = color;
    }

    function applyTextColor(color) {
        document.querySelectorAll('#nav a, #backtoblog a').forEach(function (el) {
            el.style.color = color;
        });
    }

    function applyLogo(url) {
        const preview = document.getElementById('upload_image_preview');
        if (preview) preview.innerHTML = '<img src="' + url + '" alt="">';
        const headerLogo = document.querySelector('#login h1 a');
        if (headerLogo) headerLogo.style.backgroundImage = 'url(' + url + ')';
    }

    function pickBgColor(value) {
        clearTimeout(colorTimeout);
        colorTimeout = setTimeout(function () {
            ajax('dfs_update_login_color', { color: value }).then(function (data) {
                if (data.success) applyBgColor(value);
            });
        }, 500);
    }

    function pickTextColor(value) {
        clearTimeout(colorTimeout);
        colorTimeout = setTimeout(function () {
            ajax('dfs_update_login_text_color', { color: value }).then(function (data) {
                if (data.success) applyTextColor(value);
            });
        }, 500);
    }

    function resetBgColor() {
        ajax('dfs_update_login_color', { color: cfg.defaultBg }).then(function (data) {
            if (data.success) {
                document.getElementById('bgColorPicker').value = cfg.defaultBg;
                applyBgColor(cfg.defaultBg);
            }
        });
    }

    function resetTextColor() {
        ajax('dfs_update_login_text_color', { color: cfg.defaultText }).then(function (data) {
            if (data.success) {
                document.getElementById('bgTextColorPicker').value = cfg.defaultText;
                applyTextColor(cfg.defaultText);
            }
        });
    }

    function resetLogo() {
        ajax('dfs_update_login_logo', { url: cfg.defaultUrl }).then(function (data) {
            if (data.success) applyLogo(cfg.defaultUrl);
        });
    }

    function uploadLogo() {
        if (fileFrame) {
            fileFrame.open();
            return;
        }
        fileFrame = wp.media({
            title: 'Select or Upload an Image',
            button: { text: 'Use this image' },
            multiple: false,
            library: { type: 'image' },
        });
        fileFrame.on('select', function () {
            const attachment = fileFrame.state().get('selection').first().toJSON();
            ajax('dfs_update_login_logo', { url: attachment.url }).then(function (data) {
                if (data.success) applyLogo(attachment.url);
            });
        });
        fileFrame.open();
    }

    $(document).on('ready', function () {
        const bg = document.getElementById('bgColorPicker');
        if (bg) bg.addEventListener('input', function (e) { pickBgColor(e.target.value); });

        const txt = document.getElementById('bgTextColorPicker');
        if (txt) txt.addEventListener('input', function (e) { pickTextColor(e.target.value); });

        document.querySelectorAll('[data-dfs-action]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                switch (btn.dataset.dfsAction) {
                    case 'reset-bg':     resetBgColor(); break;
                    case 'reset-text':   resetTextColor(); break;
                    case 'reset-logo':   resetLogo(); break;
                    case 'upload-logo':  uploadLogo(); break;
                }
            });
        });
    });

    if (document.readyState !== 'loading') {
        $(document).trigger('ready');
    }
})(jQuery);
