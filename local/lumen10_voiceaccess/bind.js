// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
(function() {
    'use strict';
    let token = '';
    function update() {
        const field = document.getElementById('l10va-bind-token');
        const button = document.getElementById('l10va-bind-submit');
        const valid = /^[a-f0-9]{64}$/.test(token);
        if (field && button) { field.value = valid ? token : '';button.disabled = !valid; }
        else if (valid) {
            // A new fragment on a completed pairing page needs a fresh GET form and session token.
            location.replace(location.pathname + '?prepare=1#' + token);
        }
    }
    function capture() {
        token = location.hash.slice(1);
        history.replaceState(null, '', location.pathname);
        if (document.readyState !== 'loading') { update(); }
    }
    capture();
    document.addEventListener('DOMContentLoaded', update);
    window.addEventListener('hashchange', capture);
})();
