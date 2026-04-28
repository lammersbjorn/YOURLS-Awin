# YOURLS Awin Affiliate Plugin

A YOURLS plugin that adds Awin affiliate parameters to redirects for configured merchant domains.

## Features

- Adds Awin tracking redirects for matching merchant domains.
- Supports merchant-level `campaign` and `clickref` defaults.
- Supports URL query overrides for `campaign`, `clickref`, and `clickref2` through `clickref6`.
- Keeps normal YOURLS redirects for non-matching URLs.
- Provides a YOURLS admin settings page.

## Compatibility

- YOURLS: requires 1.7.3+, tested up to 1.10.3.
- PHP: requires 7.4+, tested up to 8.5.
- Existing option key and settings shape stay compatible: `awin_affiliate_settings`.
- Redirect hook handling supports YOURLS action argument arrays and direct URL strings.

## Structure

- `plugin.php`: YOURLS plugin header, direct-access guard, runtime include, instantiation.
- `includes/class-awin-affiliate-plugin.php`: plugin runtime, admin page, settings, redirect logic.
- `tests/`: lightweight PHPUnit coverage using the YOURLS plugin test-suite bootstrap.

## Installation

1. Copy the plugin directory into `user/plugins/` in your YOURLS install.
2. Activate **Awin Affiliate** in YOURLS admin.
3. Open **Awin Affiliate Settings** from the Plugins menu.

## Testing

```bash
phpunit -c tests/phpunit.xml
php -l plugin.php
php -l includes/class-awin-affiliate-plugin.php
```

## License

MIT. See [LICENSE](LICENSE).
