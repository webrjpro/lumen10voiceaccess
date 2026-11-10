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
 * Learning flow support.
 * @module mod_lumen10voiceaccess/learning_flow
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    return {
        init: function() {
            document.querySelectorAll('[data-l10va-command]').forEach((button) => {
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
        }
    };
});
