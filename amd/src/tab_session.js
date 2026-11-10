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
 * Tab session support.
 * @module mod_lumen10voiceaccess/tab_session
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';
    /**
     * Create.
     * @param {Storage} storage Input to this operation.
     * @param {String} owner Input to this operation.
     * @param {String} site Input to this operation.
     * @param {Function} now Input to this operation.
     */
    function create(storage, owner, site, now = Date.now) {
        const key = 'lumen10-voiceaccess-continuation:' + site;

        const clear = () => {
            try {
                storage?.removeItem(key);
            } catch (error) {
                // Storage can be blocked in private/restricted browsers.
            }
        };
        /**
     * Save.
         * @param {Boolean} active Input to this operation.
         * @param {String} mode Input to this operation.
         */
        function save(active, mode) {
            if (!storage) {
                return false;
            }

            try {
                storage?.setItem(
                    key,
                    JSON.stringify({
                        owner: String(owner),
                        active: !!active,
                        mode: mode === 'device' ? 'device' : 'browser',
                        expires: now() + 60000
                    })
                );
            } catch (error) {
                return false;
            }

            return true;
        }
        /**
     * Restore.
         */
        function restore() {
            try {
                const value = JSON.parse(storage?.getItem(key) || 'null');
                clear();

                if (
                    !value ||
                    !Number.isFinite(value.expires) ||
                    value.expires <= now() ||
                    value.expires > now() + 60000 ||
                    typeof value.active !== 'boolean' ||
                    (value.owner !== String(owner) && !(value.owner === 'login' && String(owner) !== 'login'))
                ) {
                    return null;
                }

                return {
                    active: value.active,
                    mode: value.mode === 'device' ? 'device' : 'browser'
                };
            } catch (error) {
                clear();
                return null;
            }
        }

        return {
            save,
            restore,
            clear
        };
    }

    return {
        create
    };
});
