// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Access watch support.
 * @module mod_lumen10voiceaccess/access_watch
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    return {
        init: function(config) {
            let pending = false;
            let stopped = false;

            const check = async function() {
                if (pending || stopped || document.hidden) {
                    return;
                }

                pending = true;
                const abort = new AbortController();
                const timeout = setTimeout(function() {
                    abort.abort();
                }, 10000);

                try {
                    const response = await fetch(config.url, {
                        credentials: 'same-origin',
                        cache: 'no-store',
                        signal: abort.signal,
                        headers: {
                            'X-Lumen10-Owner': config.owner
                        }
                    });

                    if (!response.ok) {
                        return;
                    }

                    const result = await response.json();

                    if (result.allowed === false) {
                        stopped = true;
                        document.dispatchEvent(new Event('l10va:revoked'));
                        document.getElementById('l10va')?.remove();
                    }
                } catch (e) {
                    /* A transient connection error does not impersonate a revocation. */
                } finally {
                    clearTimeout(timeout);
                    pending = false;
                }
            };

            const timer = setInterval(function() {
                if (stopped) {
                    clearInterval(timer);
                } else {
                    check();
                }
            }, 30000);
            document.addEventListener('visibilitychange', check);
            window.addEventListener('pageshow', check);
            check();
        }
    };
});
