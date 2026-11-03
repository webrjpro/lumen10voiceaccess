// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
define([], function() {
    'use strict';
    return {init: function() {
        document.querySelectorAll('[data-l10va-command]').forEach(button => {
            button.addEventListener('click', () => {
                document.querySelector('#l10va [data-va-command="' + button.dataset.l10vaCommand + '"]')?.click();
            });
        });
        document.getElementById('l10va-flow-microphone')?.addEventListener('click', () => {
            document.querySelector('#l10va [data-va=mic]')?.click();
        });
        document.addEventListener('l10va:revoked', () => {
            document.querySelector('#l10va-learning-header a')?.focus();
            window.location.replace(window.location.href);
        });
    }};
});
