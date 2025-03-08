# YOURLS Awin Affiliate Plugin

A plugin for YOURLS that automatically adds Awin affiliate parameters to URLs from configured merchants.

## Features

- Add Awin affiliate parameters to URLs from supported merchants
- Configure your Awin affiliate ID and merchant IDs
- Support for multiple merchants with different domains
- Customizable campaign and clickref parameters
- Easy-to-use admin interface

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

## Support

For issues or feature requests, please visit the GitHub repository at https://github.com/lammersbjorn/YOURLS-Awin