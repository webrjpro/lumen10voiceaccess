# Completing a two-component upgrade

The installable release is one plugin: mod_lumen10voiceaccess. It does not require another Lumen10 plugin.

For an existing platform that also has the independent aiprovider_lumen10gemini adapter and the Concursos preparatory plugin, update those optional callers to the unified public Gemini class before completing legacy retirement. The adapter's former dependency on local_lumen10_voiceaccess must become mod_lumen10voiceaccess >= 2026111001. The reviewed adapter revision is 1.0.2 / 2026111000. These are compatibility changes to already installed products, not auxiliary installers for VoiceAccess.

The accompanying integration-upgrade.patch contains the reviewed namespace/dependency changes and no credentials or participant data. Review and apply it only if those components exist in the installation; preserve any institution-specific changes. Paths are relative to a split Moodle checkout.

After the unified upgrade and guarded native retirement of the local component, remove the old local source directory, purge caches and **run Moodle's native CLI upgrade once more**. Native plugin uninstallation invalidates Moodle's all-versions hash; the final CLI upgrade records the complete dependency state. Confirm moodle_needs_upgrading() is false, run cron and check the plugin manager before reopening the site. An empty plugin directory still counts as a discoverable component and must not remain in local/.

Back up code, database, Moodledata and private configuration before the maintenance window. Preserve records and encrypted credentials by migration; never uninstall the activity to replace it. Existing independent products retain their own configuration, consent and quotas. The technical signature and the original release tag/ZIP remain unchanged by these supplementary operations notes.
