(function () {
    'use strict';

    const cfg = window.dfsContactWidget || {};

    document.addEventListener('DOMContentLoaded', function () {
        const btn    = document.getElementById('client-help-btn');
        const form   = document.getElementById('client-help-form');
        const send   = document.getElementById('client-help-send');
        const status = document.getElementById('client-help-status');
        const subjectInput = document.getElementById('objet-textarea');
        const messageInput = document.getElementById('message-textarea');

        if (!btn || !form || !send) return;

        btn.addEventListener('click', function () {
            form.hidden = !form.hidden;
        });

        send.addEventListener('click', function () {
            const body = new URLSearchParams({
                action:  'dfs_send_mail',
                nonce:   cfg.nonce,
                subject: subjectInput.value,
                message: messageInput.value,
            });

            fetch(cfg.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body,
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data.success) {
                        alert((data.data && data.data.message) || cfg.i18n.error);
                        return;
                    }
                    status.hidden = false;
                    status.textContent = cfg.i18n.sent;
                    setTimeout(function () { status.hidden = true; }, 3000);
                });
        });
    });
})();
