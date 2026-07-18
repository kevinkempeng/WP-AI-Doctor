# PressCare AI Error Doctor

PressCare AI Error Doctor is an original, read-only WordPress diagnostics plugin by **Kevin Kemp - PressCare**. It groups noisy PHP and WordPress errors, removes sensitive data, and uses the provider-independent AI Client in WordPress 7.0 to generate cautious, structured explanations.

Version `1.0.0` is the first stable release. The plugin is not a fork and contains no inherited plugin code or branding.

## Compatibility

- WordPress: 7.0 or newer
- Tested through: WordPress 7.0.x
- PHP: 8.0 or newer
- Recommended production PHP: 8.3
- Planned CI matrix: PHP 8.0, 8.1, 8.2, 8.3, and 8.4

WordPress 7.0 is intentional: it supplies the stable, provider-independent AI Client and Connectors credential interface. Requiring PHP 8.0 provides modern language features without limiting the plugin to only the newest hosting platforms.

## Version 1.0.0 scope

1. Locate a configured WordPress or PHP error log.
2. Read only the final 2 MB.
3. Parse and group recognized PHP and WordPress error events.
4. Attribute paths to a plugin, theme, WordPress core, or an unknown component.
5. Redact common secrets and personal data before storage.
6. Store the sanitized report only for the administrator who ran it.
7. Export the sanitized report as JSON.
8. With explicit approval, request a structured explanation from the AI provider configured in WordPress.

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

## License

GPLv2 or later. See `LICENSE`.
