# Architecture

## Design goal

AI interprets evidence; it does not collect facts or change the site. The deterministic PHP layer remains useful when no provider is configured.

## Data flow

1. `LogLocator` resolves an explicitly configured local log and reads at most its final 2 MB.
2. `LogParser` extracts recognized events, classifies severity, detects component ownership, and groups repeated messages by a stable fingerprint.
3. `Redactor` replaces common paths, site URLs, email addresses, IP addresses, bearer tokens, API keys, JWTs, and named secret values.
4. `DiagnosticEngine` builds a bounded report and applies redaction again after the extension filter.
5. `AdminPage` stores the sanitized local report in user metadata for the administrator who requested it.
6. Only after explicit consent, `Analyzer` sends the environment summary and grouped sanitized samples through WordPress Core's provider-independent AI Client.
7. The AI Client routes the request to a compatible provider configured by the site owner. The plugin never receives or stores that provider's API key.

## Trust boundaries

- Raw log data is untrusted and may contain malicious strings or secrets.
- Component detection happens before redaction because paths provide useful ownership evidence.
- Every value displayed in wp-admin is escaped at output.
- AI output is untrusted, advisory text. It is escaped at output and cannot invoke plugin actions.
- All actions require `manage_options` plus a nonce.
- AI analysis also requires an explicit consent checkbox on every request.

## Extension point

`pcaied_diagnostic_report` allows integrations to add already-sanitized diagnostic facts. The complete report is redacted again after the filter. Integrations must not add raw content, credentials, or personal data.

## Planned evolution

- Broader deterministic parsing for WooCommerce and cron failures.
- Before-and-after update comparisons.
- Recurrence tracking without retaining raw logs.
- Testable provider-independent evaluation cases.
- Multisite-aware network summaries.

