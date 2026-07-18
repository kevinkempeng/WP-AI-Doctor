# PressCare AI Error Doctor

PressCare AI Error Doctor is an original, read-only WordPress diagnostics plugin by **Kevin Kemp - PressCare**. It groups noisy PHP and WordPress errors, removes sensitive data, and uses the provider-independent AI Client in WordPress 7.0 to generate cautious, structured explanations.

Version `1.1.1` is the current staging release. The plugin is not a fork and contains no inherited plugin code or branding.

## Compatibility

- WordPress: 7.0 or newer
- Tested through: WordPress 7.0.x
- PHP: 8.0 or newer
- Recommended production PHP: 8.3
- CI matrix: PHP 8.0, 8.1, 8.2, 8.3, and 8.4

WordPress 7.0 is intentional: it supplies the stable, provider-independent AI Client and Connectors credential interface. Requiring PHP 8.0 provides modern language features without limiting the plugin to only the newest hosting platforms.

## Version 1.1.1 scope

1. Locate a configured WordPress or PHP error log.
2. Read only the final 2 MB.
3. Parse and group recognized PHP and WordPress error events.
4. Separate activity from the last seven days from older log history.
5. Attribute paths to a plugin, theme, WordPress core, or an unknown component.
6. Rank current findings, group them by component, and collapse historical context.
7. Call out current critical findings with prominent labels and direct jump links.
8. Provide plain-language impact and safe resolution guidance without modifying the site.
9. Let each administrator mark findings handled until a newer occurrence reopens them.
10. Redact database identifiers, common secrets, and personal data before storage.
11. Store the sanitized report only for the administrator who ran it.
12. Export the sanitized report as JSON.
13. With explicit approval, request a focused or complete structured explanation from the AI provider configured in WordPress.
14. Let an administrator explicitly open a PressCare support request using only the sanitized finding ID, severity, and component slug.

The plugin does not edit files, alter configuration, clear logs, deactivate plugins, install updates, or apply AI-generated fixes.

## Development

Install development dependencies:

```bash
composer install
```

Run validation:

```bash
composer lint
composer test
```

Build an installable ZIP:

```bash
./build.sh
```

## Project links

- Support: https://presscare.com/contact/
- Author: https://presscare.com
- Source: https://github.com/kevinkempeng/WP-AI-Doctor

## License

GPLv2 or later. See `LICENSE`.
