=== PressCare AI Error Doctor ===
Tags: ai, debug, diagnostics, error log, site health
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Read-only WordPress error diagnostics with privacy-first, provider-independent AI explanations.

== Description ==

PressCare AI Error Doctor turns noisy WordPress and PHP error logs into a small set of understandable findings.

The local diagnostic engine:

* Reads no more than the final 2 MB of a configured log.
* Groups repeated PHP and WordPress errors by fingerprint.
* Separates activity from the last seven days from older log history.
* Groups current findings by component and ranks actionable activity ahead of historical context.
* Places active fatal errors in a dedicated first section with their safe next steps already open.
* Treats old fatal records as neutral history instead of active critical problems.
* Provides plain-language summaries, safe resolution steps, and collapsible technical evidence.
* Explains where the PHP log comes from and offers a sanitized print view that can be saved as PDF.
* Lets administrators mark findings handled; newer occurrences automatically return to the action list.
* Offers an optional PressCare support link for each sanitized finding ID.
* Identifies likely plugin, theme, or WordPress core ownership from file paths.
* Removes common filesystem paths, site URLs, database identifiers, email addresses, IP addresses, access tokens, API keys, and other secrets.
* Stores only sanitized grouped samples for the current administrator.
* Makes no changes to the site, its code, or its configuration.

When an administrator explicitly approves AI analysis, the plugin uses the provider-independent AI Client included in WordPress 7.0. WordPress routes the request to a compatible provider configured by the site owner in Settings > Connectors.

The AI response explains the evidence, separates observations from likely causes, assigns a confidence level, and recommends verification steps. Administrators can request a focused explanation for one finding or a concise brief covering the report. AI Error Doctor cannot modify files or apply fixes.

= Privacy and external services =

Local scans do not contact an external service.

AI analysis is optional and happens only after an administrator checks the consent box and requests an explanation. The plugin sends the following sanitized data to the AI provider selected by WordPress:

* WordPress and PHP versions.
* Active theme name and version.
* Counts of active plugins and available plugin updates.
* Error severity counts.
* Grouped error samples, fingerprints, occurrence counts, and detected component slugs.

The plugin does not intentionally send raw logs, posts, visitor data, login credentials, database credentials, or AI API keys. API credentials are managed by WordPress Core through Settings > Connectors.

PressCare support links are optional. When an administrator clicks a finding's support link, the browser opens presscare.com with only the sanitized finding ID, severity, and detected component slug in the URL. The plugin does not include the error sample or report, and it does not contact PressCare until the administrator clicks the link.

Depending on the provider the site owner installs and configures, data may be processed by OpenAI, Anthropic, Google, or another compatible provider. Review the terms and privacy policy of the provider you choose:

* OpenAI terms: https://openai.com/policies/terms-of-use/
* OpenAI privacy policy: https://openai.com/policies/privacy-policy/
* Anthropic commercial terms: https://www.anthropic.com/legal/commercial-terms
* Anthropic privacy policy: https://www.anthropic.com/legal/privacy
* Google API terms: https://developers.google.com/terms
* Google privacy policy: https://policies.google.com/privacy

== Installation ==

1. Upload the `presscare-ai-error-doctor` directory to `/wp-content/plugins/`, or install the ZIP through Plugins > Add New > Upload Plugin.
2. Activate PressCare AI Error Doctor.
3. Open Tools > AI Error Doctor.
4. Run a local scan.
5. Optional: install and configure a compatible AI provider under Settings > Connectors, then approve AI analysis from the report screen.

The plugin checks these log locations in order:

1. The path defined by the optional `PCAIED_LOG_PATH` constant.
2. The path configured in `WP_DEBUG_LOG`.
3. The local PHP `error_log` setting.

== Frequently Asked Questions ==

= Does the plugin change or repair my site? =

No. The plugin is read-only. It reads a bounded section of a log, creates a sanitized report, and optionally asks a connected AI provider to explain that report.

= Is an AI provider required? =

No. Local parsing, grouping, redaction, component detection, JSON export, and reporting work without an AI provider.

= Does the optional AI explanation cost money? =

Possibly. Local diagnostics are free. A connected AI provider may charge the site owner for an approved AI request according to that provider's pricing and account settings.

= Where do I enter an API key? =

PressCare AI Error Doctor does not store provider credentials. WordPress 7.0 manages provider plugins and API keys under Settings > Connectors.

= Is the complete log sent to AI? =

No. The raw log remains on the server. A focused request includes one selected sanitized group; a complete-report request can include up to 25 sanitized groups and returns no more than eight prioritized explanations.

= Can I specify a log outside wp-content? =

Yes. Define an absolute, readable local path in `wp-config.php`:

`define( 'PCAIED_LOG_PATH', '/absolute/path/to/php-error.log' );`

= What does Current critical mean? =

It counts only fatal errors with timestamps inside the current seven-day review window. Fatal errors found only in older log history are labeled Past fatal error and do not appear as active critical problems.

= Can I save an easy-to-read PDF? =

Yes. Choose View / save as PDF to open a print-friendly, sanitized report, then use the browser's Print or Save as PDF command. The document contains grouped sanitized examples, explanations, and next steps. It does not expose the complete raw log or its private server path.

== Support ==

After the plugin is listed, use its WordPress.org support forum for ordinary support and feature requests. For private or security-sensitive reports that should not include production logs or credentials, contact PressCare at https://presscare.com/contact/.

== Development ==

The complete source code, build script, automated tests, and development documentation are maintained at https://github.com/kevinkempeng/WP-AI-Doctor.

== Changelog ==

= 1.1.2 =

* Changed black summary cards to count only current critical errors, errors, and warnings.
* Moved active fatal errors into a dedicated first section with expanded explanations and resolution steps.
* Restyled older fatal records as neutral history and removed immediate-update advice from historical findings.
* Added severity-aware guidance so current warnings are not described as site-stopping emergencies.
* Added a plain-language log-source explainer and sanitized report view that can be printed or saved as PDF.

= 1.1.1 =

* Added a prominent current-critical status panel with direct jump links to every critical card.
* Strengthened critical-card labels, colors, and focus behavior while preserving current-first ranking.
* Added contextual PressCare support links that carry only sanitized finding identifiers.
* Added a clear free-guidance and optional Advanced Care support path.

= 1.1.0 =

* Reorganized findings into a prioritized action plan grouped by plugin, theme, or WordPress area.
* Collapsed historical log groups so old events no longer overwhelm current work.
* Added plain-language finding titles, impact explanations, and safe resolution steps.
* Added focused PressCare AI requests for individual findings.
* Added technical-detail toggles and a per-administrator Mark handled workflow.
* Limited complete AI briefs to the eight most actionable findings and reduced repetitive guidance.

= 1.0.2 =

* Fixed text-generation provider detection for capabilities exposed dynamically by the WordPress AI Client.
* Added regression coverage for supported and unsupported provider responses.

= 1.0.1 =

* Added recent-versus-historical event counts and clear date-range guidance.
* Expanded privacy redaction to remove database names, table identifiers, and database users.
* Added automatic re-sanitization for reports saved by earlier plugin versions.
* Added clearer AI provider readiness, consent, and possible-cost messaging.
* Added a hard output-token limit for more predictable optional AI costs.
* Refreshed the admin experience with professional PressCare AI branding and a friendlier diagnostic workflow.

= 1.0.0 =

* Initial stable release.
* Added bounded log discovery and tail reading.
* Added deterministic parsing, severity classification, component detection, grouping, and redaction.
* Added per-administrator sanitized report storage and JSON export.
* Added explicit-consent AI analysis through the native WordPress 7.0 AI Client.
* Added a read-only PressCare dashboard.
* Added suggested site privacy-policy text for the optional AI data flow.
* Linked AI setup to the correct WordPress 7.0 Connectors screen.
