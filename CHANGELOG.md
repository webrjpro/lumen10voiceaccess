# Changes

## 1.0.0-beta — 2026111001

- Consolidate global accessibility assistance and the course activity into `mod_lumen10voiceaccess`, with no local-plugin dependency.
- Migrate both legacy table-prefix generations through XMLDB renames, preserving IDs, ciphertext, approvals, device credentials and operational records.
- Transfer institutional settings and explicit role capabilities, including prohibitions; retain the existing server-secret mechanisms.
- Add guarded native retirement of the old local registration, with preflight checks before uninstallation.
- Build/minify twelve AMD modules and source maps using Moodle Grunt; provide explicit GPL headers, component metadata and English/Brazilian Portuguese login and management text.
- Require a session key and bounded shared attempt budget for phrase login; respect account lockout and restrict this method to approved local non-administrator accounts.
- Replace the optional desktop helper's policy changes and persistence with a manual visible, origin-restricted, bounded loopback speech helper.
- Complete external-data Privacy API metadata and retain private-data exclusion from course backups.
- Retain the required course index, activity-view event, permission checks, native introduction-file serving and Moodle 5.1/5.2 lifecycle compatibility.

The release remains beta pending intended-user and real-device acceptance. Automated validation does not imply Marketplace approval.
