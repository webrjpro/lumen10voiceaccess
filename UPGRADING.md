# Upgrade to the unified VoiceAccess activity

The maintenance procedure is deliberately staged. Never remove an activity instance or uninstall `mod_lumen10voiceaccess` to apply this upgrade.

1. Back up the Moodle code, database, Moodledata and private server configuration. Record the legacy and activity versions, settings, permissions, table record counts/hashes and the server-secret fingerprint privately.
2. Enable maintenance mode and suspend cron/background writers for the maintenance window.
3. Replace only `mod/lumen10voiceaccess` with the reviewed unified release. Keep the old local source present for this stage. Run Moodle's native non-interactive upgrade.
4. Confirm the five unified tables contain exactly the former records, with IDs and ciphertext unchanged. Verify institutional settings and role permissions. The schema migration supports both legacy table-prefix generations and can resume after an interrupted rename.
5. Run `php public/mod/lumen10voiceaccess/cli/retire_legacy.php` for a read-only retirement preflight. Review the result; then run the same command with `--execute` while maintenance is enabled. This invokes Moodle's native uninstaller for the **local registration only**. It refuses to proceed while a legacy table or File API record remains.
6. Preserve the old local source outside the active Moodle tree, remove its directory from `public/local`, and purge Moodle caches. The final plugin manager must show the unified activity at version `2026111001` and no installed/discoverable `local_lumen10_voiceaccess`.
7. Recheck records, encrypted credentials, server-secret access, approvals, role prohibitions, existing activity links, global assistance, privacy, scheduled tasks and normal login. Reopen the site and resume background services only after these checks pass.

For a Moodle checkout without the split layout, omit `public/` in the paths above.

The institutional Gemini secret is not moved or copied. The supported environment/configuration mechanisms and legacy protected Moodledata secret-file location remain valid. Old desktop helpers are not silently changed by a Moodle upgrade; review any separately installed Windows helper independently.

If migration detects both legacy generations or an already populated destination, stop and investigate. The plugin never merges conflicting personal records or guesses which set to discard. Restore the reviewed backup during the maintenance window if rollback is required; do not reopen a partially upgraded site.
