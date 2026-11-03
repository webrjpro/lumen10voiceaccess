# Lumen10 Acesso Universal - optional course activity

Component: `mod_lumen10voiceaccess`.
Release: **0.2.2-beta / 2026110301**. Supported Moodle branches: **5.1 and 5.2**.

This activity provides course instructions and a link to site-wide VoiceAccess
preferences. The assistant and AI integrations belong to the required companion
plugin `local_lumen10_voiceaccess`; this activity cannot provide them on its own.

## Installation

1. Install `local_lumen10_voiceaccess` version **2026110301 or later** first.
   The companion package supplied with this release is **0.5.2-beta / 2026110301**.
2. Install this activity ZIP using Moodle's native plugin installer.
3. A teacher can add **Lumen10 Acesso Universal** to a course.
4. A site administrator configures the companion plugin and authorizes participants.

Install the two ZIPs separately. Do not upload the repository source archive or a
ZIP containing both components to the single-plugin installer. Server paths inside
Moodle's public directory are `local/lumen10_voiceaccess` and `mod/lumen10voiceaccess`.

## Customer-owned AI credentials

No real API key, server credential, learner data or production settings are included.
Installing this activity does not connect customers to the author's AI account.
AI is optional and disabled by default in new companion-plugin installations.

For natural voice conversation, the institution obtains its own Google Gemini API
key and stores it in the server environment or a protected secret file outside the
public web root. There is no Gemini key field in this course activity. Students do
not need a personal Gemini key when the institution supplies one. See the companion
plugin's `INSTALLATION.md` for the exact configuration mechanisms.

Optional personal OpenAI/Anthropic keys can be entered in VoiceAccess preferences.
This is separate from Gemini Live conversation. Provider access, available models,
quotas and usage charges are the customer's responsibility. Depending on browser
configuration, browser speech services can also process audio outside Moodle.

## Scope and privacy

The activity does not restrict the assistant to a course or category. Native Moodle
access, enrolment, availability and view permissions apply. Completion by viewing
uses Moodle's native mechanism. Course backup includes the instance and introduction
files, not AI keys, personal preferences, login credentials or audio. Personal-data
handling is documented by the companion plugin.

## Release notes

0.2.2-beta updates installation and external-service documentation, corrects the
companion dependency requirement and declares Moodle 5.1/5.2 support. The companion dependency and lifecycle tests now use the canonical database schema.
Adds the required course activity index and updates lifecycle tests for Moodle 5.2. It remains a beta release, without a claim of
universal browser support or full accessibility compliance.

Copyright 2026 Carlos Antonio de Oliveira Piquet. GNU GPL v3 or later; see `LICENSE`.

