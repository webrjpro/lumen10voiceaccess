// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
define([], function() {
    'use strict';
    async function inspect(env, local, lang) {
        const results = [];
        const Recognition = env.SpeechRecognition || env.webkitSpeechRecognition;
        if (!env.isSecureContext) {
            results.push('deviceinsecure');
        } else if (!Recognition) {
            results.push('devicenospeech');
        } else if (local) {
            try {
                const probe = new Recognition();
                if (!('processLocally' in probe) || typeof Recognition.available !== 'function') {
                    results.push('devicelocalunsupported');
                } else {
                    const state = await Recognition.available({langs:[lang], processLocally:true});
                    results.push(state === 'available' ? 'devicelocalready' :
                        ['downloadable', 'downloading'].includes(state) ? 'devicelocalinstall' : 'devicelocalunsupported');
                }
            } catch (error) {
                results.push('devicelocalunsupported');
            }
        } else {
            results.push('devicespeechready');
        }
        try {
            const permission = await env.navigator.permissions.query({name:'microphone'});
            results.push({granted:'devicemicgranted', denied:'devicemicdenied', prompt:'devicemicprompt'}[permission.state] ||
                'devicemicunknown');
        } catch (error) {
            results.push('devicemicunknown');
        }
        results.push(env.speechSynthesis && env.SpeechSynthesisUtterance ?
            (env.speechSynthesis.getVoices().length ? 'devicevoicesready' : 'devicevoicespending') : 'devicenovoices');
        return results;
    }
    function init(strings) {
        const button = document.getElementById('l10va-device-check');
        const results = document.getElementById('l10va-device-results');
        if (!button || !results) {
            return;
        }
        button.addEventListener('click', async () => {
            button.disabled = true;
            results.setAttribute('aria-busy', 'true');
            let timer;
            try {
                // A browser capability query may stall; retain an honest, bounded fallback.
                const keys = await Promise.race([
                    inspect(window, document.querySelector('[name=recognitionmode]')?.value === 'device',
                        document.documentElement.lang.replace('_', '-')),
                    new Promise(resolve => {timer = setTimeout(() => resolve(['devicetimeout']), 5000);}),
                ]);
                results.replaceChildren(...keys.map(key => {
                    const item = document.createElement('li');item.textContent = strings[key];return item;
                }));
            } catch (error) {
                results.textContent = strings.devicetimeout;
            } finally {
                clearTimeout(timer);
                results.setAttribute('aria-busy', 'false');
                button.disabled = false;
            }
        });
        document.querySelector('.l10va-readiness a[href="#id_ai"]')?.addEventListener('click', () => {
            const expand = document.querySelector('#id_ai [data-bs-toggle="collapse"]');
            if (expand?.getAttribute('aria-expanded') === 'false') {
                expand.click();
            }
        });
    }
    return {inspect, init};
});
