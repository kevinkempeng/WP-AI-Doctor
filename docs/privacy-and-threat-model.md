# Privacy and threat model

## Protected data

- Filesystem paths and account names embedded in paths.
- Site URLs.
- Database names, table identifiers, and database users.
- Email and IP addresses.
- AI, bearer, and access tokens.
- Passwords and values labeled as keys, secrets, authentication, or authorization data.
- Raw error-log content.

## Controls in version 1.0.2

- Reads no more than 2 MB from the end of a local file.
- Never accepts a log path from an HTTP request.
- Requires `manage_options` and a valid nonce for scans, exports, AI calls, and deletion.
- Keeps raw log contents in memory only for the duration of a scan.
- Stores a maximum of 25 grouped findings with samples capped at 2,000 characters and eight lines.
- Redacts samples before persistent storage.
- Replaces database query bodies before a database-error sample is stored.
- Replaces database names, table identifiers, and database users with explicit redaction markers.
- Stores only the log filename, never its full path.
- Re-runs redaction after the diagnostic extension filter.
- Requires explicit consent for every AI request.
- Uses WordPress Connectors for provider credential management.
- Does not include a file editor, command runner, automated repair, or executable AI tool.

## Residual risk

No redactor can guarantee detection of every custom secret format. The dashboard shows the exact sanitized samples before the administrator approves AI analysis. Administrators should review them before continuing. Future releases should expand the redaction corpus and add regression cases for newly reported formats.

## Reporting security issues

Do not post credentials or raw production logs in a public issue. Use the support contact at https://presscare.com/contact/.
