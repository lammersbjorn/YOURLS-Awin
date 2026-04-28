<?php
/**
  * Plugin Name: Awin Affiliate
  * Description: Add your Awin affiliate parameters to URLs from configured merchants
  * Version: 2.3
  * Author: Bjorn Lammers
  * Author URI: https://github.com/lammersbjorn/YOURLS-Awin
  * License: MIT
  * License URI: https://opensource.org/licenses/MIT
  * Requires at least: YOURLS 1.7.3
  * Tested up to: YOURLS 1.10.3
  * Requires PHP: 7.4
  * Tested up to PHP: 8.5
*/

if (!defined('YOURLS_ABSPATH')) {
    die();
}

require_once __DIR__ . '/includes/class-awin-affiliate-plugin.php';

new AwinAffiliatePlugin();
