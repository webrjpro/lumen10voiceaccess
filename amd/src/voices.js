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
 * Voices support.
 * @module mod_lumen10voiceaccess/voices
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['mod_lumen10voiceaccess/speech_support'], function(SpeechSupport) {
    'use strict';
    /**
     * Init.
     * @param {Object} strings Input to this operation.
     */
    function init(strings) {
        const install = document.querySelector('[name=installvoice]');
        install?.addEventListener('click', async() => {
            install.disabled = true;
            const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            const ok = await SpeechSupport.install(Recognition, document.documentElement.lang);
            install.textContent = ok ? strings.installed : strings.localunsupported;
            install.disabled = false;
        });
        const select = document.querySelector('select[name=voiceuri]');
        const button = document.querySelector('[name=voicetest]');
        const status = document.getElementById('l10va-voice-test-status');

        const announce = (text) => {
            if (status) {
                status.textContent = text;
            }
        };

        if (!select || !button) {
            return;
        }

        select.disabled = true;
        select.replaceChildren(new Option('Gemini — Sulafat', ''));
        let audio = null;
        let preview = 0;
        let timeout;
        button.addEventListener('click', () => {
            const current = ++preview;
            clearTimeout(timeout);

            if (audio) {
                audio.pause();
                audio = null;
            }

            const finish = (text) => {
                if (current !== preview) {
                    return;
                }

                preview++;
                clearTimeout(timeout);

                if (audio) {
                    audio.pause();
                    audio = null;
                }

                announce(text);
            };

            try {
                window.speechSynthesis?.cancel();
                audio = new Audio(
                    (window.M?.cfg?.wwwroot || '') + '/mod/lumen10voiceaccess/pix/va_voicesample_sulafat.wav'
                );
                audio.volume = Math.max(0, Math.min(1, Number(document.querySelector('[name=volume]')?.value ?? 1)));
                audio.playbackRate = Math.max(
                    0.5,
                    Math.min(2, Number(document.querySelector('[name=rate]')?.value) || 1)
                );

                audio.onended = () => finish(strings.done);

                audio.onerror = () => finish(strings.failed);

                announce(strings.testing);
                timeout = setTimeout(() => {
                    finish(strings.failed);
                    window.speechSynthesis?.cancel();
                }, 15000);
                audio.play().catch(() => finish(strings.failed));
            } catch (error) {
                finish(strings.failed);
            }
        });
        window.addEventListener('pagehide', () => {
            preview++;
            clearTimeout(timeout);
            window.speechSynthesis?.cancel();

            if (audio) {
                audio.pause();
                audio = null;
            }
        });
    }

    return {
        init
    };
});
