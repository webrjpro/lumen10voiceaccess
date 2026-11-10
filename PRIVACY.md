# Personal data and external processing

The unified component stores accessibility preferences, an approved self-declaration, encrypted personal provider credentials when supplied, consent and usage counters. Pairing/device/private phrase credentials are retained as digests. The operational ledger stores bounded status/timing/counter information, with thirty-day retention dependent on cron. The plugin does not store microphone recordings, command transcripts or requested page text in these tables.

Privacy API metadata, export, context-user discovery and erasure cover these records. Exports indicate whether a key exists and do not expose the decrypted API credential or authentication digests. Revocation closes operational authorisations and removes paired credentials. A temporary application cache holds a pseudonymous network-address digest and attempt counts for up to fifteen minutes to limit anonymous sign-in attempts; it contains no phrase, raw address, recording or account identifier.

Browser speech recognition/synthesis can use browser-vendor services. Optional OpenAI/Anthropic command interpretation sends the explicitly submitted phrase and limited navigation context, such as course names. Optional institutional Gemini Live sends microphone audio to Google using a short-lived credential; requested page-reading text can also be sent. These external transmissions depend on selected features and consent; the institution must review provider terms and its own privacy notices.

Medical reports and identity documents are not collected by VoiceAccess. The approval declaration is restricted to responsible analysts. The optional Windows speech helper runs locally and accepts requests only from its configured site origin; no automatic desktop installation or browser-policy changes are performed by Moodle.

Provider API credentials, institutional configuration, billing and retention decisions belong to the institution. Source integrity signatures are technical release evidence, not legal registration or independent accessibility/security certification.
