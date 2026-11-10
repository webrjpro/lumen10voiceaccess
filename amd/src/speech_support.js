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
 * Speech support support.
 * @module mod_lumen10voiceaccess/speech_support
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';
    /**
     * Prepare.
     * @param {Function} Recognition Input to this operation.
     * @param {String} lang Input to this operation.
     * @param {Boolean} local Input to this operation.
     */
    async function prepare(Recognition, lang, local) {
        if (!Recognition) {
            return {
                error: 'unavailable'
            };
        }

        const recognition = new Recognition();
        recognition.lang = lang;

        if (!local) {
            return {
                recognition
            };
        }

        if (!('processLocally' in recognition) || typeof Recognition.available !== 'function') {
            return {
                error: 'localunsupported'
            };
        }

        try {
            const state = await Recognition.available({
                langs: [lang],
                processLocally: true
            });

            if (state !== 'available') {
                return {
                    error: state === 'downloadable' || state === 'downloading' ? 'localinstall' : 'localunsupported'
                };
            }

            recognition.processLocally = true;
            return {
                recognition
            };
        } catch (error) {
            return {
                error: 'localunsupported'
            };
        }
    }
    /**
     * Install.
     * @param {Function} Recognition Input to this operation.
     * @param {String} lang Input to this operation.
     */
    async function install(Recognition, lang) {
        if (!Recognition || typeof Recognition.install !== 'function' || typeof Recognition.available !== 'function') {
            return false;
        }

        try {
            if (
                (await Recognition.available({
                    langs: [lang],
                    processLocally: true
                })) === 'available'
            ) {
                return true;
            }

            return (
                (await Recognition.install({
                    langs: [lang],
                    processLocally: true
                })) === true
            );
        } catch (error) {
            return false;
        }
    }

    return {
        prepare,
        install
    };
});
