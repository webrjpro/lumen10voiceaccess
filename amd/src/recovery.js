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
 * Recovery support.
 * @module mod_lumen10voiceaccess/recovery
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    const ALLOWED = new Set([
        'READ_PAGE',
        'READ_HEADINGS',
        'WHERE_AM_I',
        'BRIEFING',
        'SCROLL_DOWN',
        'SCROLL_UP',
        'OPEN_COURSES',
        'OPEN_DASHBOARD',
        'OPEN_GRADES',
        'OPEN_CALENDAR',
        'OPEN_MESSAGES',
        'OPEN_NOTIFICATIONS',
        'OPEN_PROFILE',
        'OPEN_PREFERENCES',
        'READ_GRADES',
        'LIST_COURSES',
        'LIST_ACTIVITIES',
        'LIST_SECTIONS',
        'NEXT_ACTIVITY',
        'PREVIOUS_ACTIVITY',
        'NEXT_SECTION',
        'PREVIOUS_SECTION',
        'EXPAND_SECTIONS',
        'NEXT_HEADING',
        'PREVIOUS_HEADING',
        'VIDEO_PLAY',
        'VIDEO_PAUSE',
        'VIDEO_RESTART',
        'VIDEO_MUTE',
        'VIDEO_UNMUTE',
        'VIDEO_VOLUME_UP',
        'VIDEO_VOLUME_DOWN',
        'VIDEO_STATUS',
        'LIST_CONTROLS',
        'STOP',
        'HELP',
        'UNKNOWN',
        'OPEN_ITEM_1',
        'OPEN_ITEM_2',
        'OPEN_ITEM_3',
        'OPEN_ITEM_4',
        'OPEN_ITEM_5',
        'OPEN_ITEM_6',
        'OPEN_ITEM_7',
        'OPEN_ITEM_8',
        'OPEN_ITEM_9',
        'OPEN_ITEM_10',
        'OPEN_ITEM_11',
        'OPEN_ITEM_12',
        'OPEN_ITEM_13',
        'OPEN_ITEM_14',
        'OPEN_ITEM_15',
        'OPEN_ITEM_16',
        'OPEN_ITEM_17',
        'OPEN_ITEM_18',
        'OPEN_ITEM_19',
        'OPEN_ITEM_20'
    ]);
    /**
     * Create.
     * @param {Object} config Input to this operation.
     */
    function create(config) {
        const transport = config.fetch || fetch;
        const now = config.now || Date.now;
        let failures = 0;
        let openedUntil = 0;
        let pending = null;
        const cancelled = new WeakSet();
        /**
     * Interpret.
         * @param {String} phrase Input to this operation.
         * @param {String} reason Input to this operation.
         */
        async function interpret(phrase, reason = 'unknown') {
            if (pending) {
                return {
                    intent: 'UNKNOWN',
                    error: 'busy'
                };
            }

            if (now() < openedUntil) {
                return {
                    intent: 'UNKNOWN',
                    error: 'cooldown'
                };
            }

            if (
                !phrase ||
                phrase.length > 300 ||
                // eslint-disable-next-line max-len -- Keep the fixed command pattern or selector intact.
                /senha|password|api.?key|secret|token|credencia[il]|chave de api|\bsk-[A-Za-z0-9_-]{20,}\b|\bAIza[A-Za-z0-9_-]{20,}|\bAQ\.[A-Za-z0-9_.-]{20,}/i.test(
                    phrase
                )
            ) {
                return {
                    intent: 'UNKNOWN',
                    error: 'protected'
                };
            }

            const controller = new AbortController();
            pending = controller;
            const timer = setTimeout(() => controller.abort(), Math.max(10, Math.min(25000, config.timeout || 25000)));

            try {
                const response = await transport(config.endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    signal: controller.signal,
                    body: new URLSearchParams({
                        sesskey: config.sesskey,
                        command: phrase,
                        reason: ['unknown', 'targetmissing', 'recognition'].includes(reason) ? reason : 'unknown'
                    })
                });

                if (!response.ok) {
                    throw new Error('unavailable');
                }

                const result = await response.json();

                if (!result || result.error || !ALLOWED.has(result.intent)) {
                    if (result?.error === 'limited') {
                        openedUntil = now() + 60000;
                    }

                    throw new Error('unavailable');
                }

                failures = 0;
                config.onstate?.('ready');
                return {
                    intent: result.intent
                };
            } catch (error) {
                if (cancelled.has(controller)) {
                    return {
                        intent: 'UNKNOWN',
                        error: 'cancelled'
                    };
                }

                failures++;

                if (failures >= 3) {
                    openedUntil = now() + 60000;
                }

                config.onstate?.(now() < openedUntil ? 'cooldown' : 'local');
                return {
                    intent: 'UNKNOWN',
                    error: 'unavailable'
                };
            } finally {
                clearTimeout(timer);
                pending = null;
            }
        }

        return {
            interpret,
            cancel: () => {
                if (pending) {
                    cancelled.add(pending);
                    pending.abort();
                }
            },
            status: () => ({
                failures,
                cooldown: Math.max(0, openedUntil - now()),
                pending: !!pending
            })
        };
    }

    return {
        create
    };
});
