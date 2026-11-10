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
 * Bind support.
 * @module mod_lumen10voiceaccess/bind
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
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
