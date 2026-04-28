# YOURLS Awin Affiliate Plugin

A YOURLS plugin that adds Awin affiliate parameters to URLs from configured merchants.

## Features

- Add Awin affiliate parameters to URLs from supported merchants
- Configure your Awin affiliate ID and merchant IDs
- Support for multiple merchants with different domains
- Customizable campaign and clickref parameters
- Easy-to-use admin interface

## Compatibility

- YOURLS: requires 1.7.3+, tested up to 1.10.3
- PHP: requires 7.4+, tested up to 8.5
- Handles both YOURLS redirect hook argument arrays and direct URL strings for older/custom hook callers.
- Avoids known PHP 8.1+ null/array-to-string warning paths in redirect parameter handling.

## Installation

1. Download the plugin
2. Place the plugin folder in your `user/plugins` directory
3. Activate the plugin in the YOURLS admin interface

## Configuration

1. Go to "Awin Affiliate Settings" in your YOURLS admin panel
2. Enter your Awin Affiliate ID
3. Configure your merchants with their Awin Merchant IDs and domains
4. Save your settings

## How It Works

When a user clicks a shortened URL that matches a configured merchant domain:

1. The plugin detects the merchant
2. It creates a properly formatted Awin affiliate link
3. The user is redirected through Awin's tracking system
4. You earn commission on qualifying purchases

## Testing

Run the lightweight PHPUnit suite:

```bash
phpunit -c tests/phpunit.xml
```

For plugin changes, also run:

```bash
php -l plugin.php
```

## License

MIT. See [LICENSE](LICENSE).

## Support

For issues or feature requests, please visit the GitHub repository at https://github.com/lammersbjorn/YOURLS-Awin
