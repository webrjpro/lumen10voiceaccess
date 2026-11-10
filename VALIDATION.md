# VoiceAccess unified release validation

Component **mod_lumen10voiceaccess**, release **1.0.0-beta**, build **2026111001**. No required auxiliary plugin.

| Check | Executed result |
| --- | --- |
| Moodle 5.1.8 / PHP 8.2.35 / MariaDB 11.4.13 | 82 native PHPUnit tests, 416 assertions; passed |
| Moodle 5.2.4+ / PHP 8.3.36 / MariaDB 11.4.13 | 82 native PHPUnit tests, 416 assertions; passed |
| JavaScript unit/regression suite | 93 tests; passed |
| Native browser workflow without other Lumen10 products | 38 checks; passed, no JavaScript runtime errors |
| Rehearsed upgrade and legacy retirement | Stored record IDs/data, encrypted credential, institutional settings and server-secret fingerprint preserved; only the activity remains |
| Moodle Grunt / AMD | Twelve modules minified, source maps verified, lint/build passed |
| PHP and translations | 61 PHP files; 443 keys in each language; syntax and placeholder parity passed |
| Official moodle-plugin-ci | Required files, callback names, table prefixes and PHP lint passed on Linux / PHP 8.3.36 |
| Native Moodle ZIP validator | Passed; course index present; beta maturity and existing installation are expected warnings |
| Release integrity | ZIP CRC, exact source bytes, SHA-256 and detached Ed25519 signature verified |

The same 82 native cases ran in each Moodle branch. Tests used synthetic accounts/data. The isolated browser environment retained no active local VoiceAccess plugin and contained no other Lumen10 product. External AI calls were blocked and no actual provider request was made by this review.

The browser exercised anonymous/native login, session-key rejection, successful migrated-phrase login, HttpOnly device cookie, prevention of account switching, transferred analyst access, management labels, protected settings/helper download, private-key non-disclosure, device readiness, preferences at 320/390/720/1366 pixels, native course index, original activity ID, global student learning navigation, unapproved-user denial and absence of old local endpoints. Three real interface screenshots accompany the delivery.

Native tests cover the command/provider boundary, encryption and isolated preferences, explicit approvals, privacy, device/pairing/phrase records, learning navigation, operational quotas/revocation, native activity lifecycle/backup and migration from both legacy prefix generations. The migration tests include record IDs/ciphertext, interrupted migration, conflicting destinations and preservation of role prohibitions.

Limits: MariaDB was executed; PostgreSQL and load were not rerun in this release. Speech/browser APIs and playback used synthetic doubles. Real microphones, intended users, provider model access/charges and end-to-end external AI were not validated here. No independent WCAG/security certification or Marketplace approval is claimed. The release remains beta pending real-device and intended-user acceptance.

ZIP SHA-256: `c83d822199245a8cd8adc53db920b1905137f7931c00a4675ccfa5cc0b176a4b`.

Technical references: [activity modules](https://moodledev.io/docs/5.2/apis/plugintypes/mod), [JavaScript modules](https://moodledev.io/docs/5.2/guides/javascript/modules), [Privacy API](https://moodledev.io/docs/5.2/apis/subsystems/privacy). GitHub publication and production deployment are recorded separately in the delivery receipt.
