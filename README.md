# Lumen10 VoiceAccess

**Voice and keyboard assistance throughout Moodle, with one installable activity plugin.**

Release **1.0.0-beta / 2026111001** · Component **mod_lumen10voiceaccess** · Moodle **5.1 and 5.2** · GNU GPL v3 or later.

VoiceAccess supports people who use keyboard navigation, spoken feedback or assisted commands. It provides labelled controls, page/headings reading, focus navigation, dictation in permitted fields, confirmation before consequential actions, display preferences and a simplified learning journey. Speech recognition, audio playback and microphone permissions depend on the browser and device. No universal accessibility certification is claimed.

## One plugin

Install **mod_lumen10voiceaccess-1.0.0-beta.zip** using Moodle's plugin installer, or extract its `lumen10voiceaccess` directory to `public/mod/lumen10voiceaccess` in a split Moodle checkout. No Composer/npm command is required on the administrator's server.

The assistant is available through site-wide Moodle hooks for authorised participants even when no course activity has been created. An activity instance can provide a convenient course entry. **The former local_lumen10_voiceaccess plugin is no longer required.** Guardian IA, Prova, Admissions and other Lumen10 products are independent and are not bundled.

## Initial setup

1. Back up code, database and Moodledata before installation or upgrade.
2. Open Site administration > Plugins > Activity modules > Lumen10 VoiceAccess. Review enabled features, permissions and privacy disclosures. Optional external AI starts disabled in a fresh installation.
3. Responsible analysts manage individual self-declarations and approvals at `/mod/lumen10voiceaccess/manage.php`. Ordinary users require approval and the use capability; managers do not automatically acquire participant access. Site administrators retain diagnostic access.
4. Approved participants use `/mod/lumen10voiceaccess/preferences.php` for their preferences and device readiness check. The readiness check does not record audio or make a provider request.
5. Optionally add VoiceAccess to a course using Moodle's normal activity chooser.

## Upgrade from the former two-component distribution

The existing `mod_lumen10voiceaccess` activity is updated in place; its activity IDs, course links and introductory files are retained. The native migration renames the five legacy local tables to activity-prefixed tables, preserving record IDs, encrypted personal API credentials, approvals, device/pairing/phrase digests, usage and operational history. It supports both original `local_l10va_*` names and the later `local_lumen10_voiceaccess_*` names.

The migration checks every table pair before changing it and refuses ambiguous populated destinations. It copies institutional settings into the unified component and transfers explicit role permissions, including prohibitions. It disables legacy hooks after migration. Server environment variables, the private `$CFG->local_lumen10_voiceaccess_geminikey` setting and the protected `Moodledata/local_lumen10_voiceaccess/secrets/gemini.key` location remain supported unchanged; no secret is copied into plugin source.

After the normal upgrade, verify records and permissions. In maintenance mode, the provided `cli/retire_legacy.php` performs a read-only preflight by default; `--execute` uses Moodle's native API to retire the old local registration. It refuses retirement if unmigrated tables or legacy File API records remain. Keep the old source in a private backup outside Moodle, remove `public/local/lumen10_voiceaccess` from the active code tree, purge caches and verify that only the unified component remains before reopening the site. **Do not uninstall the activity or delete legacy tables before migration.** See [UPGRADING.md](UPGRADING.md).

## Privacy and optional external services

Local commands do not require an AI subscription. Browser speech recognition can send audio to the browser vendor depending on device configuration. Optional command interpretation sends the submitted command and limited navigation context to the selected provider. Institutional Gemini Live sends microphone audio directly to Google using a short-lived token; requested page-reading text may also be sent. See [PRIVACY.md](PRIVACY.md).

The institution supplies its own Google project and server-side Gemini credential. When permitted, users may provide an encrypted personal OpenAI or Anthropic API key for command interpretation. Provider accounts, model availability, quotas and charges are the institution's responsibility. Local attempt limits do not guarantee a monetary budget. No real credential or participant list is distributed. Existing production choices are preserved on upgrade.

The plugin does not retain audio or transcripts. Privacy API metadata, export and erasure cover stored personal data. Device cleanup and operational retention depend on Moodle cron. Private sign-in phrases are credentials, **not biometric speaker verification**; use a long unique phrase. Phrase login is rate limited, requires a valid session key and respects account lockout. It does not authenticate administrators or bypass external SSO; those accounts use their native sign-in method.

## Optional Windows helper

VoiceAccess does not require a desktop program. Analysts may download an optional Windows speech helper from the management screen. Downloading does not execute it. The reviewed helper runs manually in a visible window, listens only on loopback for the configured site origin, bounds request/text sizes and stops when closed. It does not change browser policies, bypass microphone permissions, install startup entries or use an execution-policy bypass. Previously installed helpers are outside Moodle's installer; institutions should review and remove any obsolete desktop installation separately.

## Validation and support

See the release's validation report for the exact tests executed. Synthetic speech and automated checks do not establish real-microphone acceptance, provider billing limits, universal WCAG/PDF-UA compliance or Moodle Marketplace approval. This remains a beta pending real-device and intended-user acceptance.

- Source/releases: https://github.com/webrjpro/lumen10voiceaccess
- Issue tracker: https://github.com/webrjpro/lumen10voiceaccess/issues
- Moodle APIs: https://moodledev.io/docs/5.2/apis/plugintypes/mod
- Hooks: https://moodledev.io/docs/5.2/apis/core/hooks
- JavaScript build: https://moodledev.io/docs/5.2/guides/javascript/modules
- Privacy API: https://moodledev.io/docs/5.2/apis/subsystems/privacy

Copyright 2026 Carlos Antonio de Oliveira Piquet, for original contributions. Release manifests are signed using a technical Ed25519 key. This verifies release integrity against the key; it is not an INPI registration or ICP-Brasil certificate and does not limit rights granted by the GPL.
