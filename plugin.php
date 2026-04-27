<?php
/**
  * Plugin Name: Awin Affiliate
  * Description: Add your Awin affiliate parameters to URLs from configured merchants
  * Version: 2.3
  * Author: Bjorn Lammers
  * Author URI: https://github.com/lammersbjorn/YOURLS-Awin
  * License: BSD 3-Clause
  * License URI: https://opensource.org/licenses/BSD-3-Clause
  * Requires at least: YOURLS 1.7.3
  * Tested up to: YOURLS 1.10.2
  * Requires PHP: 7.4
  * Tested up to PHP: 8.5
*/

// Prevent direct access to this file
if (!defined('YOURLS_ABSPATH')) die();

class AwinAffiliatePlugin
{
    private const AWIN_BASE_URL = 'https://www.awin1.com/cread.php';
    private const CLICKREF_COUNT = 6;
    private const OPTION_NAME = 'awin_affiliate_settings';

    private $settings;
    private $merchants;

    public function __construct()
    {
        // Initialize the plugin
        yourls_add_action('plugins_loaded', [$this, 'init']);

        // Admin page hooks
        yourls_add_action('plugins_loaded', [$this, 'addAdminPage']);
        yourls_add_action('admin_page_awin_affiliate_settings', [$this, 'displayAdminPage']);

        // Process form submissions
        yourls_add_action('admin_init', [$this, 'processFormSubmission']);

        // URL processing hook. `pre_redirect` is a filter — YOURLS calls it
        // with the long URL as the first argument (not an args array), so we
        // register the callback as a single-argument receiver here.
        yourls_add_action('pre_redirect', [$this, 'processUrl'], 10);
    }

    /**
     * Initialize plugin settings
     */
    public function init(): void
    {
        // Load settings from YOURLS options
        $stored = yourls_get_option(self::OPTION_NAME);

        if (!is_array($stored)) {
            // Default settings
            $this->settings = [
                'awinaffid'        => '',
                'default_campaign' => '',
                'default_clickref' => '',
                'merchants'        => [
                    'coolblue' => [
                        'name'    => 'Coolblue',
                        'awinmid' => '',
                        'domains' => [
                            'coolblue.nl',
                            'coolblue.be',
                            'coolblue.de',
                            'coolblue.fr'
                        ],
                        'enabled' => true
                    ]
                ]
            ];

            yourls_add_option(self::OPTION_NAME, $this->settings);
            return;
        }

        // Existing install — make sure the expected top-level keys are present
        // so the rest of the plugin can rely on them without `??` everywhere.
        $stored += [
            'awinaffid'        => '',
            'default_campaign' => '',
            'default_clickref' => '',
            'merchants'        => [],
        ];
        if (!is_array($stored['merchants'])) {
            $stored['merchants'] = [];
        }
        $this->settings = $stored;
    }

    /**
     * Add admin page to YOURLS menu
     */
    public function addAdminPage(): void
    {
        yourls_register_plugin_page('awin_affiliate_settings', 'Awin Affiliate Settings', [$this, 'displayAdminPage']);
    }

    /**
     * Display admin settings page
     */
    public function displayAdminPage(): void
    {
        if (!yourls_is_admin()) die('Access denied');

        $nonce     = yourls_create_nonce('awin_affiliate_settings');
        $awinaffid = (string) ($this->settings['awinaffid'] ?? '');
        $merchants = is_array($this->settings['merchants'] ?? null) ? $this->settings['merchants'] : [];
?>
        <div class="awin-page">
        <h2>Awin Affiliate Settings</h2>

        <form method="post">
            <input type="hidden" name="nonce" value="<?php echo yourls_esc_attr($nonce); ?>">
            <input type="hidden" name="action" value="update_awin_settings">

            <h3>Global Settings</h3>
            <div class="settings-group">
                <p>
                    <label for="awinaffid">Awin Affiliate ID (required):</label><br>
                    <input type="text" id="awinaffid" name="awinaffid" value="<?php echo yourls_esc_attr($awinaffid); ?>" class="text" required>
                </p>
            </div>

            <h3>Merchants</h3>
            <div class="merchants-container">
                <?php foreach ($merchants as $id => $merchant): ?>
                    <?php
                        $idAttr   = yourls_esc_attr((string) $id);
                        $name     = (string) ($merchant['name']     ?? $id);
                        $awinmid  = (string) ($merchant['awinmid']  ?? '');
                        $campaign = (string) ($merchant['campaign'] ?? '');
                        $clickref = (string) ($merchant['clickref'] ?? '');
                        $enabled  = !empty($merchant['enabled']);
                        $domains  = is_array($merchant['domains'] ?? null)
                            ? array_filter($merchant['domains'], 'is_string')
                            : [];
                    ?>
                    <div class="merchant-settings">
                        <div class="merchant-header">
                            <h4><?php echo yourls_esc_html($name); ?></h4>
                            <label class="merchant-toggle">
                                <input type="checkbox" name="merchant_enabled[<?php echo $idAttr; ?>]" value="1"
                                    <?php echo $enabled ? 'checked' : ''; ?>>
                                Enable
                            </label>
                        </div>
                        <div class="merchant-content">
                            <div class="merchant-row">
                                <div class="merchant-col">
                                    <label for="merchant_awinmid_<?php echo $idAttr; ?>">Awin Merchant ID:</label>
                                    <input type="text" id="merchant_awinmid_<?php echo $idAttr; ?>"
                                        name="merchant_awinmid[<?php echo $idAttr; ?>]"
                                        value="<?php echo yourls_esc_attr($awinmid); ?>"
                                        class="text">
                                </div>
                                <div class="merchant-col">
                                    <label for="merchant_campaign_<?php echo $idAttr; ?>">Default Campaign:</label>
                                    <input type="text" id="merchant_campaign_<?php echo $idAttr; ?>"
                                        name="merchant_campaign[<?php echo $idAttr; ?>]"
                                        value="<?php echo yourls_esc_attr($campaign); ?>"
                                        class="text">
                                </div>
                                <div class="merchant-col">
                                    <label for="merchant_clickref_<?php echo $idAttr; ?>">Default Clickref:</label>
                                    <input type="text" id="merchant_clickref_<?php echo $idAttr; ?>"
                                        name="merchant_clickref[<?php echo $idAttr; ?>]"
                                        value="<?php echo yourls_esc_attr($clickref); ?>"
                                        class="text">
                                </div>
                            </div>
                            <div class="merchant-row">
                                <div class="merchant-col full">
                                    <label for="merchant_domains_<?php echo $idAttr; ?>">Domains (one per line):</label>
                                    <textarea id="merchant_domains_<?php echo $idAttr; ?>" name="merchant_domains[<?php echo $idAttr; ?>]" rows="3" class="text"><?php
                                        echo yourls_esc_html(implode("\n", $domains));
                                    ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($merchants)): ?>
                    <p>No merchants configured yet — fill in the form below to add one.</p>
                <?php endif; ?>
            </div>

            <h3>Add New Merchant</h3>
            <div class="settings-group">
                <div class="merchant-row">
                    <div class="merchant-col">
                        <label for="new_merchant_name">Merchant Name:</label>
                        <input type="text" id="new_merchant_name" name="new_merchant_name" class="text">
                    </div>
                    <div class="merchant-col">
                        <label for="new_merchant_awinmid">Awin Merchant ID:</label>
                        <input type="text" id="new_merchant_awinmid" name="new_merchant_awinmid" class="text">
                    </div>
                </div>
                <div class="merchant-row">
                    <div class="merchant-col">
                        <label for="new_merchant_campaign">Default Campaign:</label>
                        <input type="text" id="new_merchant_campaign" name="new_merchant_campaign" class="text">
                    </div>
                    <div class="merchant-col">
                        <label for="new_merchant_clickref">Default Clickref:</label>
                        <input type="text" id="new_merchant_clickref" name="new_merchant_clickref" class="text">
                    </div>
                </div>
                <div class="merchant-row">
                    <div class="merchant-col full">
                        <label for="new_merchant_domains">Domains (one per line):</label>
                        <textarea id="new_merchant_domains" name="new_merchant_domains" rows="3" class="text"></textarea>
                    </div>
                </div>
            </div>

            <p><input type="submit" value="Save Settings" class="button button-primary"></p>
        </form>
        </div><!-- .awin-page -->

        <style>
            /* All selectors are scoped to .awin-page so the plugin styles
               don't bleed into the surrounding YOURLS / Sleeky chrome. Colors
               use rgba()/inherit so the form looks right in vanilla YOURLS
               and in Sleeky's light + dark themes. */
            .awin-page .settings-group {
                margin: 20px 0;
                padding: 15px;
                border: 1px solid rgba(128, 128, 128, 0.2);
                border-radius: 5px;
                background: transparent;
            }

            .awin-page .merchants-container {
                margin: 20px 0;
                display: flex;
                flex-direction: column;
                gap: 15px;
            }

            .awin-page .merchant-settings {
                border: 1px solid rgba(128, 128, 128, 0.2);
                border-radius: 5px;
                background: transparent;
            }

            .awin-page .merchant-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px 15px;
                border-bottom: 1px solid rgba(128, 128, 128, 0.2);
                background-color: rgba(128, 128, 128, 0.05);
            }

            .awin-page .merchant-header h4 {
                margin: 0;
                font-size: 1em;
            }

            .awin-page .merchant-content {
                padding: 15px;
            }

            .awin-page .merchant-row {
                display: flex;
                gap: 15px;
                margin-bottom: 15px;
            }

            .awin-page .merchant-row:last-child {
                margin-bottom: 0;
            }

            .awin-page .merchant-col {
                flex: 1;
                min-width: 0;
            }

            .awin-page .merchant-col.full {
                flex: 0 0 100%;
            }

            .awin-page .merchant-col label {
                display: block;
                margin-bottom: 5px;
                font-weight: 600;
            }

            .awin-page input.text,
            .awin-page textarea.text {
                width: 100%;
                padding: 8px;
                border: 1px solid rgba(128, 128, 128, 0.3);
                border-radius: 3px;
                background: transparent;
                color: inherit;
                box-sizing: border-box;
                font: inherit;
            }

            .awin-page input.text:focus,
            .awin-page textarea.text:focus {
                outline: 2px solid rgba(0, 128, 255, 0.4);
                outline-offset: 1px;
            }

            .awin-page input:required:invalid {
                border-color: #f44336;
            }

            .awin-page .merchant-toggle {
                display: flex;
                align-items: center;
                gap: 5px;
                font-weight: normal;
            }

            @media (max-width: 768px) {
                .awin-page .merchant-row {
                    flex-direction: column;
                    gap: 10px;
                }
            }
        </style>
<?php
    }

    /**
     * Process admin form submission
     */
    public function processFormSubmission(): void
    {
        if (!isset($_POST['action']) || $_POST['action'] !== 'update_awin_settings') {
            return;
        }

        // Only react to admin POSTs.
        if (!yourls_is_admin()) {
            return;
        }

        // Verify nonce. Pulls the value from $_REQUEST['nonce'] when called
        // with only the action name; dies on failure (default behavior).
        yourls_verify_nonce('awin_affiliate_settings');

        // Update global settings. Cast every $_POST read to string so
        // unexpected payload shapes (arrays, missing keys) do not trip
        // PHP 8.1+ deprecations or 8.4+ type errors.
        $this->settings['awinaffid'] = trim((string) ($_POST['awinaffid'] ?? ''));

        // Update existing merchants
        foreach ($this->settings['merchants'] as $id => &$merchant) {
            $merchant['enabled']  = isset($_POST['merchant_enabled'][$id]);
            $merchant['awinmid']  = trim((string) ($_POST['merchant_awinmid'][$id]  ?? ''));
            $merchant['campaign'] = trim((string) ($_POST['merchant_campaign'][$id] ?? ''));
            $merchant['clickref'] = trim((string) ($_POST['merchant_clickref'][$id] ?? ''));
            $merchant['domains']  = $this->normaliseDomainList(
                (string) ($_POST['merchant_domains'][$id] ?? '')
            );
        }
        unset($merchant); // break the foreach reference — long-standing PHP gotcha

        // Add new merchant if provided
        $newName    = trim((string) ($_POST['new_merchant_name']    ?? ''));
        $newAwinmid = trim((string) ($_POST['new_merchant_awinmid'] ?? ''));
        if ($newName !== '' && $newAwinmid !== '') {
            $id = yourls_sanitize_title($newName);
            if ($id !== '' && !isset($this->settings['merchants'][$id])) {
                $this->settings['merchants'][$id] = [
                    'name'     => $newName,
                    'awinmid'  => $newAwinmid,
                    'campaign' => trim((string) ($_POST['new_merchant_campaign'] ?? '')),
                    'clickref' => trim((string) ($_POST['new_merchant_clickref'] ?? '')),
                    'domains'  => $this->normaliseDomainList(
                        (string) ($_POST['new_merchant_domains'] ?? '')
                    ),
                    'enabled'  => true
                ];
            }
        }

        // Save settings
        yourls_update_option(self::OPTION_NAME, $this->settings);
        yourls_add_notice('Settings updated successfully');
    }

    /**
     * Turn a textarea blob (one domain per line) into a clean,
     * lower-cased, deduplicated list of domains.
     */
    private function normaliseDomainList(string $blob): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $blob);
        if (!is_array($lines)) {
            return [];
        }
        $out = [];
        foreach ($lines as $line) {
            $line = strtolower(trim($line));
            if ($line !== '' && !in_array($line, $out, true)) {
                $out[] = $line;
            }
        }
        return array_values($out);
    }

    /**
     * Process URL for redirection. YOURLS' `pre_redirect` filter passes the
     * destination URL as the first argument; earlier versions of this plugin
     * incorrectly treated it as an array, which silently broke the redirect
     * rewrite for everyone. Accept either form for safety.
     *
     * @param mixed $url URL string from YOURLS (or — for legacy callers —
     *                   an array whose first element is the URL).
     */
    public function processUrl($url): void
    {
        try {
            if (is_array($url)) {
                $url = $url[0] ?? '';
            }
            if (!is_string($url) || $url === '') {
                return;
            }

            $merchant = $this->findMatchingMerchant($url);
            if ($merchant && !empty($merchant['enabled'])) {
                $this->handleRedirect($url, $merchant);
            }
        } catch (Throwable $e) {
            error_log('Awin Affiliate Plugin Error: ' . $e->getMessage());
        }
    }

    /**
     * Find matching merchant for URL
     * @param string $url
     * @return array|null
     */
    private function findMatchingMerchant(string $url): ?array
    {
        $parsedUrl = parse_url($url);
        if (!is_array($parsedUrl) || empty($parsedUrl['host'])) {
            return null;
        }

        $host = strtolower((string) $parsedUrl['host']);
        // Strip a single leading "www." so "www.coolblue.nl" matches a stored
        // "coolblue.nl" entry. preg_replace can return null on regex error,
        // so coalesce back to $host to keep the rest of the function safe.
        $stripped = preg_replace('/^www\./', '', $host);
        $host = is_string($stripped) ? $stripped : $host;

        $merchants = $this->settings['merchants'] ?? [];
        if (!is_array($merchants)) {
            return null;
        }

        foreach ($merchants as $merchant) {
            $domains = $merchant['domains'] ?? [];
            if (!is_array($domains)) {
                continue;
            }
            foreach ($domains as $domain) {
                if (is_string($domain) && strtolower($domain) === $host) {
                    return $merchant;
                }
            }
        }

        return null;
    }

    /**
     * Handle the redirect process
     */
    private function handleRedirect(string $url, array $merchant): void
    {
        $urlParts = parse_url($url);
        if (!is_array($urlParts) || empty($urlParts['host'])) {
            return; // malformed URL — let YOURLS' default redirect path run.
        }
        $baseUrl = $this->buildBaseUrl($urlParts);

        // Get existing parameters
        $existingParams = [];
        if (isset($urlParts['query']) && is_string($urlParts['query'])) {
            parse_str($urlParts['query'], $existingParams);
        }

        // Build Awin parameters. Cast every value to string so urlencode()
        // never sees a null (which is a deprecation warning in PHP 8.1+).
        $params = [
            'awinmid'   => (string) ($merchant['awinmid'] ?? ''),
            'awinaffid' => (string) ($this->settings['awinaffid'] ?? ''),
        ];

        // Add campaign if provided in URL or merchant settings
        if (isset($existingParams['campaign'])) {
            $params['campaign'] = (string) $existingParams['campaign'];
        } elseif (!empty($merchant['campaign'])) {
            $params['campaign'] = (string) $merchant['campaign'];
        }

        // Add clickrefs
        if (isset($existingParams['clickref'])) {
            $params['clickref'] = (string) $existingParams['clickref'];
        } elseif (!empty($merchant['clickref'])) {
            $params['clickref'] = (string) $merchant['clickref'];
        }

        // Add additional clickrefs if provided in URL
        for ($i = 2; $i <= self::CLICKREF_COUNT; $i++) {
            $key = "clickref{$i}";
            if (isset($existingParams[$key])) {
                $params[$key] = (string) $existingParams[$key];
            }
        }

        $this->outputRedirectPage($baseUrl, $params);
        die();
    }
    /**
     * Build the base URL without query parameters
     * @param array $urlParts Parsed URL parts
     * @return string
     */
    private function buildBaseUrl(array $urlParts): string
    {
        $scheme = $urlParts['scheme'] ?? 'https';
        $host = $urlParts['host'];
        $path = $urlParts['path'] ?? '';

        return sprintf('%s://%s%s', $scheme, $host, $path);
    }

    /**
     * Output the redirect page
     * @param string $url Original URL
     * @param array $params Awin parameters
     */
    private function outputRedirectPage(string $url, array $params): void
    {
        // Build query parameters
        $queryParams = array_map(
            fn($key, $value) => $key . '=' . urlencode($value),
            array_keys($params),
            array_values($params)
        );

        // Add encoded destination URL
        $queryParams[] = 'ued=' . urlencode($url);

        // Build final URL
        $finalUrl = self::AWIN_BASE_URL . '?' . implode('&', $queryParams);

        // Output redirect page
        header('Content-Type: text/html; charset=utf-8');
        header('X-Robots-Tag: noindex, nofollow');

        echo $this->getRedirectTemplate($finalUrl);
    }

    /**
     * Get the HTML template for redirect page
     * @param string $finalUrl The final Awin URL
     * @return string
     */
    private function getRedirectTemplate(string $finalUrl): string
    {
        $escapedUrl = htmlspecialchars($finalUrl, ENT_QUOTES, 'UTF-8');
        $jsonUrl = json_encode($finalUrl);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Redirecting...</title>
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="refresh" content="0; url={$escapedUrl}">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
               line-height: 1.5; padding: 2rem; text-align: center; }
        .container { max-width: 600px; margin: 0 auto; }
        .spinner { width: 40px; height: 40px; margin: 20px auto;
                  border: 3px solid #f3f3f3; border-top: 3px solid #3498db;
                  border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); }
                         100% { transform: rotate(360deg); } }
    </style>
    <script>
        window.location.href = {$jsonUrl};
    </script>
</head>
<body>
    <div class="container">
        <h1>Redirecting...</h1>
        <div class="spinner"></div>
        <p>If you are not redirected automatically, <a href="{$escapedUrl}">click here</a>.</p>
    </div>
</body>
</html>
HTML;
    }
}

// Initialize the plugin
new AwinAffiliatePlugin();
