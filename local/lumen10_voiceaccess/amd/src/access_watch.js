// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
define([], function() {
    'use strict';
    return {init: function(config) {
        let pending = false;
        let stopped = false;
        const check = async function() {
            if (pending || stopped || document.hidden) { return; }
            pending = true;
            const abort = new AbortController();
            const timeout = setTimeout(function() { abort.abort(); }, 10000);
            try {
                const response = await fetch(config.url, {credentials:'same-origin',cache:'no-store',signal:abort.signal,
                    headers:{'X-Lumen10-Owner':config.owner}});
                if (!response.ok) { return; }
                const result = await response.json();
                if (result.allowed === false) {
                    stopped = true;
                    document.dispatchEvent(new Event('l10va:revoked'));
                    document.getElementById('l10va')?.remove();
                }
            } catch (e) { /* A transient connection error does not impersonate a revocation. */ }
            finally {clearTimeout(timeout);pending = false;}
        };
        const timer = setInterval(function() { if (stopped) { clearInterval(timer); } else { check(); } }, 30000);
        document.addEventListener('visibilitychange', check);
        window.addEventListener('pageshow', check);
        check();
    }};
});
