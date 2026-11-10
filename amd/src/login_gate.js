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
 * Login gate support.
 * @module mod_lumen10voiceaccess/login_gate
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['mod_lumen10voiceaccess/tab_session'], function(TabSession) {
    const STORAGE_KEY = 'l10va_skip_comorbidity';

    const norm = (s) =>
        String(s || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9\s]/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();

    const stripFiller = (s) =>
        norm(s)
            .replace(
                /^(?:sim\s+)?(?:a\s+minha\s+frase\s+e|minha\s+frase\s+e|a\s+frase\s+e|frase\s+cadastrada\s+e|frase)\s+/i,
                ''
            )
            .trim();
    /**
     * Init.
     * @param {Object} cfg Input to this operation.
     */
    function init(cfg) {
        if (!cfg || !cfg.endpoint) {
            return;
        }

        const strings = cfg.strings;

        if (!strings) {
            return;
        }

        const WELCOME_PROMPT = strings.gate_welcome;
        const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
        let state = 'IDLE',
            rec = null,
            active = false,
            speaking = false,
            speakTimer = null;
        let pendingPrompt = '',
            verification = null,
            revision = 0,
            speechRevision = 0,
            currentAudio = null;
        let recognitionRunning = false,
            recognitionStarting = false;
        const box = document.createElement('div');
        box.className = 'l10va-login-gate';
        box.innerHTML =
            // eslint-disable-next-line max-len -- Keep the fixed command pattern or selector intact.
            '<div class="fw-semibold"></div><div id="l10va-login-gate-status" role="status" aria-live="polite"></div><div class="l10va-login-gate-actions"><div class="form-check mb-0"><input class="form-check-input" type="checkbox" id="l10va-skip-comorbidity"><label class="form-check-label small" for="l10va-skip-comorbidity"></label></div><button type="button" id="l10va-voice-login-btn" class="l10va-reask-btn"></button><button type="button" id="l10va-decline-btn" class="l10va-reask-btn"></button></div>';
        box.querySelector('.fw-semibold').textContent = strings.gate_title;
        box.querySelector('label').textContent = strings.gate_remember;
        box.querySelector('#l10va-voice-login-btn').textContent = strings.gate_voice;
        box.querySelector('#l10va-decline-btn').textContent = strings.gate_conventional;
        const loginFormWrap =
            document.getElementById('theme_boost_union-loginform') ||
            document.querySelector('.login-form, form#login, .login-container');

        if (loginFormWrap && loginFormWrap.parentNode) {
            loginFormWrap.parentNode.insertBefore(box, loginFormWrap);
        } else {
            (document.querySelector('#region-main') || document.body).prepend(box);
        }

        const statusEl = box.querySelector('#l10va-login-gate-status');
        const checkEl = box.querySelector('#l10va-skip-comorbidity');
        const conventionalCookie = 'L10VAConventional';
        const siteUrl = new URL(cfg.root || window.location.href);
        const cookiePath = siteUrl.pathname.replace(/\/?$/, '/');
        const siteDomain = siteUrl.hostname.replace(/^(?:www|cursos)\./, '');
        const cookieDomain =
            siteDomain.includes('.') &&
            !/^[\d.]+$/.test(siteDomain) &&
            (window.location.hostname === siteDomain || window.location.hostname.endsWith('.' + siteDomain))
                ? '; Domain=' + siteDomain
                : '';

        const isConventional = () =>
            document.cookie.split(';').some((value) => value.trim() === conventionalCookie + '=1');

        const setConventional = (enabled) => {
            const options =
                '; Path=' + cookiePath + '; SameSite=Lax' + (window.location.protocol === 'https:' ? '; Secure' : '');

            if (cookieDomain) {
                document.cookie = conventionalCookie + '=; Max-Age=0' + options;
            } // Zero remembers an explicit reopening across aliases, overriding older localStorage.

            document.cookie =
                conventionalCookie + '=' + (enabled ? '1' : '0') + '; Max-Age=' + 90 * 86400 + options + cookieDomain;
        };

        const restoreConventionalCaptcha = () => {
            const already = isConventional();
            setConventional(true);

            if (cfg.captchaExempt && !already && isConventional() && document.querySelector('form#login')) {
                window.location.reload();
            }
        };

        const loginForm = document.querySelector('form#login');
        const captcha = loginForm?.querySelector('.login-form-recaptcha, .g-recaptcha');

        if (captcha) {
            const message = document.createElement('div');
            message.id = 'l10va-login-captcha-status';
            message.className = 'alert alert-warning';
            message.setAttribute('role', 'alert');
            message.hidden = true;
            captcha.parentNode.insertBefore(message, captcha);
            loginForm.addEventListener(
                'submit',
                (event) => {
                    const response = loginForm.querySelector('[name="g-recaptcha-response"]');

                    if (isConventional() && !response?.value.trim()) {
                        event.preventDefault();
                        event.stopImmediatePropagation();
                        message.textContent = strings.gate_captcha;
                        message.hidden = false;
                        const target = captcha.querySelector('iframe') || captcha;
                        target.setAttribute('tabindex', '0');
                        target.focus();
                    } else {
                        message.hidden = true;
                    }
                },
                true
            );
        }

        const declineBtn = box.querySelector('#l10va-decline-btn');
        const voiceBtn = box.querySelector('#l10va-voice-login-btn');
        const reaskBtn = document.createElement('button');
        reaskBtn.type = 'button';
        reaskBtn.id = 'l10va-reask-btn';
        reaskBtn.className = 'l10va-reask-btn';
        reaskBtn.textContent = strings.gate_accessibility;
        box.id = 'l10va-login-gate';
        reaskBtn.setAttribute('aria-controls', box.id);
        const forgotLink = document.querySelector(
            '.login-form-forgotpassword a, a[href*="/login/forgot_password.php"]'
        );
        const links = forgotLink?.parentElement || document.createElement('div');
        links.classList.add('l10va-login-links');
        links.appendChild(reaskBtn);

        if (!forgotLink) {
            (loginFormWrap || box.parentNode).appendChild(links);
        }

        const setStatus = (msg) => {
            if (statusEl) {
                statusEl.textContent = msg;
            }
        };

        const markDoneSpeaking = (delay) => {
            clearTimeout(speakTimer);
            speakTimer = setTimeout(() => {
                speaking = false;
            }, delay);
        };

        const speak = (text) => {
            if (!text) {
                return;
            }

            silencePrompt();
            setStatus(text === WELCOME_PROMPT ? strings.gate_answer : text);
            const speech = ++speechRevision;
            speaking = true;
            const estMs = Math.max(3200, Math.min(22000, text.length * 85));
            markDoneSpeaking(estMs);

            const getAudioUrl = (phrase) => {
                if (!cfg.audio || (cfg.lang && !cfg.lang.toLowerCase().startsWith('pt'))) {
                    return null;
                }

                if (phrase === WELCOME_PROMPT || phrase.startsWith('Olá. Você precisa')) {
                    return cfg.audio.welcome;
                }

                if (phrase.includes('Qual é a sua frase')) {
                    return cfg.audio.whichphrase;
                }

                if (phrase.includes('Sua resposta foi gravada')) {
                    return cfg.audio.confirmed;
                }

                if (phrase.includes('Login por voz cancelado')) {
                    return cfg.audio.cancelled;
                }

                if (phrase.includes('não corresponde a um acesso')) {
                    return cfg.audio.invalid;
                }

                return null;
            };

            let fallbackStarted = false;

            const writtenFallback = (t, s) => {
                if (s !== speechRevision || box.hidden || fallbackStarted) {
                    return;
                }

                fallbackStarted = true;

                if (currentAudio) {
                    currentAudio.pause();
                    currentAudio = null;
                } // Gemini recording unavailable: retain the written prompt without machine TTS.

                pendingPrompt = t;
                markDoneSpeaking(400);
            };

            const audioUrl = getAudioUrl(text);

            if (audioUrl) {
                try {
                    if (currentAudio) {
                        currentAudio.pause();
                        currentAudio.currentTime = 0;
                    }

                    const audio = new Audio(audioUrl);
                    currentAudio = audio;

                    audio.onended = () => {
                        if (speech === speechRevision) {
                            currentAudio = null;
                            markDoneSpeaking(300);
                        }
                    };

                    audio.onerror = () => {
                        writtenFallback(text, speech);
                    };

                    const playPromise = audio.play();

                    if (playPromise) {
                        playPromise.catch(() => {
                            writtenFallback(text, speech);
                        });
                    }

                    return;
                } catch (e) {
                    writtenFallback(text, speech);
                    return;
                }
            } // One output owner. The Windows bridge remains available for recognition only.

            writtenFallback(text, speech);
        };

        const silencePrompt = () => {
            speechRevision++;
            pendingPrompt = '';
            clearTimeout(speakTimer);
            speaking = false;

            if (currentAudio) {
                try {
                    currentAudio.pause();
                    currentAudio.currentTime = 0;
                } catch (e) {}

                currentAudio = null;
            }

            try {
                window.speechSynthesis?.cancel();
            } catch (e) {}

            fetch('http://127.0.0.1:10110/stop', {
                method: 'POST',
                mode: 'cors'
            }).catch(() => {});
        };

        const stopGate = (msg) => {
            active = false;
            state = 'DISABLED';
            revision++;
            verification?.abort();
            verification = null;
            box.hidden = true;
            reaskBtn.setAttribute('aria-expanded', 'false');
            silencePrompt();

            try {
                rec?.stop();
            } catch (e) {}

            if (msg) {
                setStatus(msg);
            }
        };

        const verifyPhrase = async(rawPhrase) => {
            const cleaned = stripFiller(rawPhrase);

            if (!cleaned || cleaned.length < 3) {
                return false;
            }

            state = 'VERIFYING';
            verification?.abort();
            const request = new AbortController();
            verification = request;
            const current = revision;
            setStatus(strings.gate_checking);
            let failedRequest = false;

            try {
                const r = await fetch(cfg.endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    signal: request.signal,
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: new URLSearchParams({
                        phrase: cleaned,
                        sesskey: cfg.sesskey || ''
                    }).toString()
                });

                if (!r.ok) {
                    throw new Error('unavailable');
                }

                const res = await r.json();

                if (!active || current !== revision) {
                    return false;
                }

                if (res && res.ok) {
                    active = false;

                    try {
                        rec?.stop();
                    } catch (e) {}

                    if (TabSession && typeof TabSession.create === 'function' && res.userid) {
                        TabSession.create(window.sessionStorage, String(res.userid), cfg.root).save(true, 'browser');
                    }

                    const welcome =
                        strings.gate_confirmed + (res.firstname ? ', ' + res.firstname : '') + strings.gate_entering;
                    speak(welcome);
                    setTimeout(() => {
                        if (current === revision) {
                            window.location.assign(res.redirect || cfg.root + '/my/');
                        }
                    }, 1200);
                    return true;
                }
            } catch (e) {
                failedRequest = true;
            }

            if (!active || current !== revision) {
                return false;
            }

            state = 'ASK_PHRASE';
            speak(failedRequest ? strings.gate_network : strings.gate_invalid);
            return false;
        };

        const onSpeech = (raw) => {
            if (!active) {
                return;
            }

            const t = norm(raw);

            if (
                !t ||
                t.includes('precisa de ajuda para acessar') ||
                t.includes('diga sim para usar') ||
                t.includes('assistencia de acessibilidade') ||
                t.includes('mobilidade reduzida') ||
                t.includes('caso contrario diga nao') ||
                t.includes('qual e a sua frase') ||
                t.includes('frase nao reconhecida') ||
                t.includes('a frase recebida') ||
                t.includes('nao foi possivel consultar')
            ) {
                return;
            }

            if (speaking) {
                silencePrompt();
                speaking = false;
            }

            if (
                (t.includes('responder novamente') || t.includes('perguntar novamente')) &&
                !t.includes('nao perguntar')
            ) {
                setConventional(false);
                checkEl.checked = false;

                try {
                    localStorage.removeItem(STORAGE_KEY);
                } catch (e) {}

                startGate();
                return;
            }

            if (t.includes('nao perguntar novamente') || t.includes('nao perguntar mais')) {
                checkEl.checked = true;

                try {
                    localStorage.setItem(STORAGE_KEY, '1');
                } catch (e) {}

                speak(strings.gate_remembered);
                stopGate(strings.gate_disabled);
                restoreConventionalCaptcha();
                return;
            }

            if (state === 'ASK_COMORBIDITY') {
                if (/^(?:no|cancel|nao|nao preciso|nao tenho|normal|login normal|sem comorbidade|cancelar)\b/.test(t)) {
                    try {
                        localStorage.setItem(STORAGE_KEY, '1');
                    } catch (e) {}

                    stopGate(strings.gate_declined);
                    restoreConventionalCaptcha();
                    return;
                }

                if (/^(?:yes|sim|preciso|tenho|possuo|quero|afirmativo|comorbidade|acesso por voz)\b/.test(t)) {
                    const remainder = stripFiller(t).replace(/^(?:sim|yes)\s+/, '');

                    if (
                        remainder &&
                        remainder.split(' ').length >= 2 &&
                        !/^(?:sim|preciso|tenho|possuo|comorbidade|acesso por voz)$/.test(remainder)
                    ) {
                        verifyPhrase(remainder);
                        return;
                    }

                    state = 'ASK_PHRASE';
                    speak(strings.gate_phrase);
                    return;
                }

                if (t.split(' ').length >= 2) {
                    verifyPhrase(t);
                }

                return;
            }

            if (state === 'ASK_PHRASE') {
                if (/^(?:cancel|back|no|cancelar|voltar|nao|login normal|sair)$/.test(t)) {
                    speak(strings.gate_cancelled);
                    stopGate(strings.gate_help);
                    return;
                }

                verifyPhrase(raw);
            }
        };

        const ensureRec = () => {
            if (!SR) {
                setStatus(strings.gate_unsupported);
                return;
            }

            if (!active || recognitionRunning || recognitionStarting) {
                return;
            }

            if (!rec) {
                rec = new SR();
                rec.continuous = true;
                rec.interimResults = false;
                rec.lang = cfg.lang || 'pt-BR';

                rec.onstart = () => {
                    recognitionStarting = false;
                    recognitionRunning = true;

                    if (state === 'ASK_PHRASE' && !speaking) {
                        setStatus(strings.gate_listening);
                    }
                };

                rec.onresult = (e) => {
                    if (speaking) {
                        return;
                    }

                    const last = e.results[e.results.length - 1];

                    if (last && last.isFinal !== false && last[0] && last[0].transcript) {
                        onSpeech(last[0].transcript);
                    }
                };

                rec.onend = () => {
                    recognitionRunning = false;
                    recognitionStarting = false;

                    if (active) {
                        const current = revision;
                        setTimeout(() => {
                            if (!active || current !== revision) {
                                return;
                            }

                            ensureRec();
                        }, 250);
                    }
                };

                rec.onerror = (event) => {
                    recognitionRunning = false;
                    recognitionStarting = false;

                    if (!active || state === 'VERIFYING') {
                        return;
                    }

                    if (event.error === 'no-speech') {
                        if (!speaking) {
                            setStatus(strings.gate_nospeech);
                        }

                        return;
                    }

                    active = false;
                    silencePrompt();
                    const error = event.error || '';
                    setStatus(
                        ['not-allowed', 'service-not-allowed'].includes(error)
                            ? strings.gate_permission
                            : error === 'audio-capture'
                              ? strings.gate_capture
                              : strings.gate_recognition
                    );
                };
            }

            try {
                recognitionStarting = true;
                rec.start();
            } catch (e) {
                recognitionStarting = false;

                if (e.name !== 'InvalidStateError') {
                    rec.onerror({
                        error: 'not-allowed'
                    });
                }
            }
        };

        voiceBtn.addEventListener('click', () => {
            revision++;
            verification?.abort();
            silencePrompt();
            active = true;
            state = 'ASK_PHRASE';
            setStatus(strings.gate_starting);
            ensureRec();

            if (recognitionRunning) {
                setStatus(strings.gate_listening);
            }
        });

        const startGate = () => {
            revision++;
            verification?.abort();
            box.hidden = false;
            reaskBtn.setAttribute('aria-expanded', 'true');
            active = true;
            state = 'ASK_COMORBIDITY';
            speak(WELCOME_PROMPT);
            ensureRec();
        };

        let skip = isConventional();

        try {
            const rememberedChoice = document.cookie
                .split(';')
                .some((value) => value.trim().startsWith(conventionalCookie + '='));

            if (!rememberedChoice) {
                skip = localStorage.getItem(STORAGE_KEY) === '1';
            }
        } catch (e) {}

        checkEl.checked = skip;
        checkEl.addEventListener('change', () => {
            try {
                if (checkEl.checked) {
                    localStorage.setItem(STORAGE_KEY, '1');
                } else {
                    localStorage.removeItem(STORAGE_KEY);
                }
            } catch (e) {}

            if (checkEl.checked) {
                stopGate();
                restoreConventionalCaptcha();
                reaskBtn.focus();
            } else {
                setConventional(false);
            }
        });
        declineBtn.addEventListener('click', () => {
            // A negative answer stays dismissed on this device until explicitly reopened.
            try {
                localStorage.setItem(STORAGE_KEY, '1');
            } catch (e) {}

            stopGate();
            reaskBtn.focus();
            restoreConventionalCaptcha();
        });
        reaskBtn.addEventListener('click', () => {
            setConventional(false);
            checkEl.checked = false;

            try {
                localStorage.removeItem(STORAGE_KEY);
            } catch (e) {}

            startGate();
            declineBtn.focus();
        });
        document
            .querySelectorAll('#username, #password, input[name="username"], input[name="password"]')
            .forEach((inp) => {
                inp.addEventListener('keydown', (e) => {
                    if (active && e.isTrusted && e.key && e.key.length === 1) {
                        stopGate(strings.gate_typing);
                    }
                });
            });
        ['pointerdown', 'keydown'].forEach((ev) =>
            window.addEventListener(
                ev,
                (e) => {
                    if (
                        !active ||
                        (e.target &&
                            (e.target.id === 'username' ||
                                e.target.id === 'password' ||
                                box.contains(e.target) ||
                                e.target.id === 'l10va-reask-btn'))
                    ) {
                        return;
                    }

                    ensureRec();

                    if (pendingPrompt) {
                        const p = pendingPrompt;
                        pendingPrompt = '';
                        speak(p);
                    }
                },
                {
                    passive: true
                }
            )
        );

        if (skip) {
            box.hidden = true;
            reaskBtn.setAttribute('aria-expanded', 'false');
            restoreConventionalCaptcha();
        } else {
            startGate();
        }
    }

    return {
        init
    };
});
