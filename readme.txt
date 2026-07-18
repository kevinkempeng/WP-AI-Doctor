=== PressCare AI Error Doctor ===
Tags: ai, debug, diagnostics, error log, site health
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Read-only WordPress error diagnostics with privacy-first, provider-independent AI explanations.

== Description ==

PressCare AI Error Doctor turns noisy WordPress and PHP error logs into a small set of understandable findings.

The local diagnostic engine:

* Reads no more than the final 2 MB of a configured log.
* Groups repeated PHP and WordPress errors by fingerprint.
* Separates activity from the last seven days from older log history.
* Identifies likely plugin, theme, or WordPress core ownership from file paths.
* Removes common filesystem paths, site URLs, database identifiers, email addresses, IP addresses, access tokens, API keys, and other secrets.
* Stores only sanitized grouped samples for the current administrator.
* Makes no changes to the site, its code, or its configuration.

When an administrator explicitly approves AI analysis, the plugin uses the provider-independent AI Client included in WordPress 7.0. WordPress routes the request to a compatible provider configured by the site owner in Settings > Connectors.

The AI response explains the evidence, separates observations from likely causes, assigns a confidence level, and recommends verification steps. It cannot modify files or apply fixes.

= Privacy and external services =

Local scans do not contact an external service.

AI analysis is optional and happens only after an administrator checks the consent box and clicks "Explain with AI." The plugin sends the following sanitized data to the AI provider selected by WordPress:

* WordPress and PHP versions.
* Active theme name and version.
* Counts of active plugins and available plugin updates.
* Error severity counts.
* Grouped error samples, fingerprints, occurrence counts, and detected component slugs.

The plugin does not intentionally send raw logs, posts, visitor data, login credentials, database credentials, or AI API keys. API credentials are managed by WordPress Core through Settings > Connectors.

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

No. The raw log remains on the server. Only the sanitized environment summary and up to 25 grouped samples are included in an approved AI request.

= Can I specify a log outside wp-content? =

Yes. Define an absolute, readable local path in `wp-config.php`:

`define( 'PCAIED_LOG_PATH', '/absolute/path/to/php-error.log' );`

== Support ==

After the plugin is listed, use its WordPress.org support forum for ordinary support and feature requests. For private or security-sensitive reports that should not include production logs or credentials, contact PressCare at https://presscare.com/contact/.

== Development ==

The complete source code, build script, automated tests, and development documentation are maintained at https://github.com/kevinkempeng/WP-AI-Doctor.

== Changelog ==

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
