# Changelog

All notable changes to PressCare AI Error Doctor are documented here.

## 1.1.1 - 2026-07-18

- Added a prominent status panel that identifies current critical findings and links directly to each card.
- Strengthened critical-card coloring, labels, and keyboard focus behavior.
- Added optional PressCare support links containing only the sanitized finding ID, severity, and component slug.
- Added an unobtrusive free-guidance versus Advanced Care path on the plugin screen.

## 1.1.0 - 2026-07-18

- Reorganized findings into current, historical, and handled sections ranked by actionable priority.
- Grouped findings by the plugin, theme, WordPress area, or unidentified source involved.
- Added plain-language titles, impact explanations, safe resolution steps, and collapsible technical evidence.
- Added focused AI requests for one selected finding while preserving explicit consent.
- Added a per-administrator handled state that automatically reopens when a newer occurrence appears.
- Limited complete AI briefs to eight actionable findings and instructed providers to avoid repetitive guidance.

## 1.0.2 - 2026-07-18

- Fixed text-generation provider detection for methods exposed dynamically by the WordPress AI Client.
- Added regression coverage for supported and unsupported provider capability responses.

## 1.0.1 - 2026-07-18

- Added recent, historical, and undated event classification with human-readable date guidance.
- Redacted database names, table identifiers, and database users before storage, export, or optional AI use.
- Automatically re-sanitized reports saved by earlier versions before display, export, or optional AI use.
- Added regression coverage for the expanded privacy boundary.
- Clarified provider readiness, consent, and possible provider charges.
- Added a hard output-token limit and tighter response schema for predictable AI usage.
- Redesigned the admin screen with a polished PressCare AI workflow and roadmap panel.

## 1.0.0 - 2026-07-18

- Prepared the first stable WordPress.org release.
- Added suggested site privacy-policy text for the optional AI data flow.
- Confirmed the distribution remains read-only and provider-independent.
- Corrected the WordPress 7.0 Settings > Connectors admin URL.

## 0.1.0 - 2026-07-18

- Started a new, original PressCare plugin codebase.
- Added the read-only diagnostic engine.
- Added bounded log discovery and tail reading.
- Added deterministic error grouping and component detection.
- Added privacy redaction before report storage or AI use.
- Added per-administrator reports and sanitized JSON export.
- Added explicit-consent analysis through the WordPress 7.0 AI Client.
- Added the PressCare admin dashboard and responsive styles.
- Added unit-test fixtures, coding standards, and a PHP 8.0-8.4 CI matrix.
