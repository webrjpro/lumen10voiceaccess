# Installation and customer-owned AI credentials

Component: `local_lumen10_voiceaccess`.
Release: **0.5.2-beta / 2026110301**. Supported Moodle branches: **5.1 and 5.2**.

This is the main VoiceAccess plugin. The optional `mod_lumen10voiceaccess` activity
provides an entry point in a course; it cannot provide the assistant on its own.

## Installation

1. Install the `local_lumen10_voiceaccess` ZIP through Moodle's native installer.
   The source directory belongs at `public/local/lumen10_voiceaccess` in Moodle 5.1/5.2.
2. Optionally install the separate `mod_lumen10voiceaccess` ZIP.
3. Open Site administration > Plugins > Local plugins > Lumen10 VoiceAccess.
4. Review user approval and device requirements. Authorize participants at
   `/local/lumen10_voiceaccess/manage.php`.
5. Leave AI disabled to use the available local/browser commands. Speech recognition,
   synthesis, microphone permissions and local language support depend on the browser.

The distribution includes no production database, Moodledata, Gemini secret file,
API key, private signing key or preconfigured institutional user list. On a new
installation, `allowai`, `gemini_enabled` and `gemini_live_enabled` default to disabled.

## Institutional Gemini: the customer provides the key

A server administrator obtains a key for the customer's own Google project:
https://ai.google.dev/gemini-api/docs/api-key

Use one of the supported server-side mechanisms:

- Set `LUMEN10_GEMINI_API_KEY` in the Moodle web-service environment.
- Set `LUMEN10_GEMINI_API_KEY_FILE` to a protected file outside the public web root.
- Store the key in the default protected file:
  `$CFG->dataroot/local_lumen10_voiceaccess/secrets/gemini.key`.
- Alternatively, define `$CFG->local_lumen10_voiceaccess_geminikey` in Moodle's
  active private configuration.

Use exactly the customer's key as the file contents. Restrict file access to the
Moodle service and responsible server administrators. Keep it out of plugin source
directories, Git, screenshots and public archives. Do not send it to the plugin
author. Ensure the web service and relevant CLI processes load the intended settings.
There is no Gemini key field in the optional course activity.

Then enable institutional AI (`allowai`), Gemini (`gemini_enabled`) and, if desired,
voice conversation (`gemini_live_enabled`). Select models available to the customer's
project. Configure both text and Live audiences: pilot mode requires the customer's
chosen usernames. Audience selection does not replace individual access approval
and participant consent. Review state and limits at
`/local/lumen10_voiceaccess/gemini.php`. An explicit connection test makes a real
provider request and can consume quota.

Usage is charged to the customer's project when applicable. Local request limits
do not guarantee a monetary spending cap. Configure project budgets and review
provider quotas separately. The installer does not enable provider billing.

The permanent Gemini key stays server-side. For browser voice conversation, the
server requests a short-lived, single-use token, and the browser sends microphone
audio directly to Google. The temporary token is visible to its client; the permanent
API key is not returned to the browser. See:
https://ai.google.dev/gemini-api/docs/live-api/ephemeral-tokens

## Optional personal OpenAI or Anthropic key

When allowed by the institution, an authorized person opens
`/local/lumen10_voiceaccess/index.php`, selects a provider and model, enters their
own API key and consents to processing. The key is encrypted using Moodle's encryption
mechanism. Leaving the key field empty preserves the stored key; the remove-key
control deletes it. This feature interprets navigation commands and is separate
from institutional Gemini Live voice conversation.

## External processing and limitations

With optional AI enabled, command text and limited navigation context, such as course
names, can be sent to the selected provider. Gemini Live receives microphone audio;
a requested page-reading action may send page text. Browser speech services can also
process audio externally. Review data policies and provider terms before enabling
these features. No provider request is needed merely to install these ZIPs.

This beta does not guarantee universal accessibility, browser support or exam fraud
prevention. Test real devices and intended users before wider deployment. The optional
Windows helper is not required to install the plugin and is not run by the installer.

## Distribution release and upgrade

0.5.2-beta fixes the moodle-plugin-ci table-prefix validation failure. All five tables
now use the full local_lumen10_voiceaccess prefix, including runtime queries, privacy
operations and cleanup tasks. Native Moodle upgrade renames legacy local_l10va tables,
preserving record IDs, encrypted personal keys, approvals, paired devices and usage.
The institution's Gemini server secret is not moved or copied.

The migration checks every table pair before renaming. If both old and new versions
of the same table exist, it stops rather than merge or discard records. A partially
completed rename can be retried. Use Moodle's normal upgrade process; do not uninstall
an existing plugin to apply this correction. Take the institution's normal database
and Moodledata backup before an upgrade. No production upgrade was performed while
preparing this package.

This revision remains beta. Package hashes and a detached technical release signature
accompany the installers separately. See packages/qa-results.json in the distribution
repository for the checks actually executed.

Copyright 2026 Carlos Antonio de Oliveira Piquet. GNU GPL v3 or later.
