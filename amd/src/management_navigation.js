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
 * Management navigation support.
 * @module mod_lumen10voiceaccess/management_navigation
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    return {
        init: function(config) {
            if (!document.body.classList.contains('lumen-modern-admin')) {
                return;
            }

            const insert = function() {
                const nav = document.querySelector('#lex-admin-drawer .lex-admin-categories');

                if (!nav) {
                    return false;
                }

                if (document.getElementById('l10va-manage-menu')) {
                    return true;
                }

                const link = document.createElement('a');
                link.id = 'l10va-manage-menu';
                link.className = 'lex-admin-category l10va-manage-link';
                link.href = config.url;
                const icon = document.createElement('img');
                icon.src = config.icon;
                icon.alt = '';
                icon.width = 22;
                icon.height = 22;
                link.append(icon, document.createTextNode(config.title));
                nav.append(link);
                return true;
            };

            if (insert()) {
                return;
            }

            const observer = new MutationObserver(function() {
                if (insert()) {
                    observer.disconnect();
                }
            });
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
            setTimeout(function() {
                observer.disconnect();
            }, 20000);
        }
    };
});
