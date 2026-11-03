// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
// Short-lived, tab-scoped continuation. No credentials, audio, phrases or actions are stored.
define([], function() {
    'use strict';
    function create(storage, owner, site, now = Date.now) {
        const key = 'lumen10-voiceaccess-continuation:' + site;
        const clear = () => {
            try {
                storage?.removeItem(key);
            } catch (error) {
                // Storage can be blocked in private/restricted browsers.
            }
        };
        function save(active, mode) {
            if (!storage) {
                return false;
            }
            try {
                storage?.setItem(key, JSON.stringify({owner:String(owner),active:!!active,
                    mode:mode === 'device' ? 'device' : 'browser',expires:now()+60000}));
            } catch (error) {
                return false;
            }
            return true;
        }
        function restore() {
            try {
                const value = JSON.parse(storage?.getItem(key) || 'null');
                clear();
                if (!value || !Number.isFinite(value.expires) || value.expires <= now() ||
                        value.expires > now()+60000 || typeof value.active !== 'boolean' ||
                        (value.owner !== String(owner) && !(value.owner === 'login' && String(owner) !== 'login'))) {
                    return null;
                }
                return {active:value.active,mode:value.mode === 'device' ? 'device' : 'browser'};
            } catch (error) {
                clear();
                return null;
            }
        }
        return {save,restore,clear};
    }
    return {create};
});
