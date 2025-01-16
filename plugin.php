<?php
/*
Plugin Name: Awin Affiliate
Description: Add your Awin affiliate parameters to URLs from configured merchants
Version: 2.3
Author: Bjorn Lammers
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

        // URL processing hook
        yourls_add_action('pre_redirect', [$this, 'processUrl']);
    }

    /**
     * Initialize plugin settings
     */
    public function init(): void
    {
        // Load settings from YOURLS options
        $this->settings = yourls_get_option(self::OPTION_NAME);

        if ($this->settings === false) {
            // Default settings
            $this->settings = [
                'awinaffid' => '',
                'default_campaign' => '',
                'default_clickref' => '',
                'merchants' => []
            ];

            // Add default merchants
            $this->settings['merchants'] = [
                'coolblue' => [
                    'name' => 'Coolblue',
                    'awinmid' => '',
                    'domains' => [
                        'coolblue.nl',
                        'coolblue.be',
                        'coolblue.de',
                        'coolblue.fr'
                    ],
                    'enabled' => true
                ]
            ];

            yourls_add_option(self::OPTION_NAME, $this->settings);
        }
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

        $nonce = yourls_create_nonce('awin_affiliate_settings');
?>
        <h2>Awin Affiliate Settings</h2>

        <form method="post">
            <input type="hidden" name="nonce" value="<?php echo $nonce; ?>">
            <input type="hidden" name="action" value="update_awin_settings">

            <h3>Global Settings</h3>
            <div class="settings-group">
                <p>
                    <label for="awinaffid">Awin Affiliate ID (required):</label><br>
                    <input type="text" id="awinaffid" name="awinaffid" value="<?php echo htmlspecialchars($this->settings['awinaffid']); ?>" class="text" required>
                </p>
            </div>

            <h3>Merchants</h3>
            <div class="merchants-container">
                <?php foreach ($this->settings['merchants'] as $id => $merchant): ?>
                    <div class="merchant-settings">
                        <div class="merchant-header">
                            <h4><?php echo htmlspecialchars($merchant['name']); ?></h4>
                            <label class="merchant-toggle">
                                <input type="checkbox" name="merchant_enabled[<?php echo $id; ?>]" value="1"
                                    <?php echo $merchant['enabled'] ? 'checked' : ''; ?>>
                                Enable
                            </label>
                        </div>
                        <div class="merchant-content">
                            <div class="merchant-row">
                                <div class="merchant-col">
                                    <label for="merchant_awinmid_<?php echo $id; ?>">Awin Merchant ID:</label>
                                    <input type="text" id="merchant_awinmid_<?php echo $id; ?>"
                                        name="merchant_awinmid[<?php echo $id; ?>]"
                                        value="<?php echo htmlspecialchars($merchant['awinmid']); ?>"
                                        class="text">
                                </div>
                                <div class="merchant-col">
                                    <label for="merchant_campaign_<?php echo $id; ?>">Default Campaign:</label>
                                    <input type="text" id="merchant_campaign_<?php echo $id; ?>"
                                        name="merchant_campaign[<?php echo $id; ?>]"
                                        value="<?php echo htmlspecialchars($merchant['campaign'] ?? ''); ?>"
                                        class="text">
                                </div>
                                <div class="merchant-col">
                                    <label for="merchant_clickref_<?php echo $id; ?>">Default Clickref:</label>
                                    <input type="text" id="merchant_clickref_<?php echo $id; ?>"
                                        name="merchant_clickref[<?php echo $id; ?>]"
                                        value="<?php echo htmlspecialchars($merchant['clickref'] ?? ''); ?>"
                                        class="text">
                                </div>
                            </div>
                            <div class="merchant-row">
                                <div class="merchant-col full">
                                    <label>Domains (one per line):</label>
                                    <textarea name="merchant_domains[<?php echo $id; ?>]" rows="3" class="text"><?php
                                                                                                                echo htmlspecialchars(implode("\n", $merchant['domains']));
                                                                                                                ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
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

            <p><input type="submit" value="Save Settings" class="button primary"></p>
        </form>

        <style>
            .settings-group {
                margin: 20px 0;
                padding: 15px;
                border: 1px solid rgba(128, 128, 128, 0.2);
                border-radius: 5px;
            }

            .merchants-container {
                margin: 20px 0;
                display: flex;
                flex-direction: column;
                gap: 15px;
            }

            .merchant-settings {
                border: 1px solid rgba(128, 128, 128, 0.2);
                border-radius: 5px;
            }

            .merchant-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px 15px;
                border-bottom: 1px solid rgba(128, 128, 128, 0.2);
            }

            .merchant-header h4 {
                margin: 0;
            }

            .merchant-content {
                padding: 15px;
            }

            .merchant-row {
                display: flex;
                gap: 15px;
                margin-bottom: 15px;
            }

            .merchant-row:last-child {
                margin-bottom: 0;
            }

            .merchant-col {
                flex: 1;
            }

            .merchant-col.full {
                flex: 0 0 100%;
            }

            .merchant-col label {
                display: block;
                margin-bottom: 5px;
            }

            input.text,
            textarea.text {
                width: 100%;
                padding: 5px;
                border: 1px solid rgba(128, 128, 128, 0.2);
                border-radius: 3px;
                background: transparent;
                color: inherit;
            }

            .merchant-toggle {
                display: flex;
                align-items: center;
                gap: 5px;
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

        // Verify nonce
        yourls_verify_nonce('awin_affiliate_settings');

        // Update global settings
        $this->settings['awinaffid'] = $_POST['awinaffid'];

        // Update existing merchants
        foreach ($this->settings['merchants'] as $id => &$merchant) {
            $merchant['enabled'] = isset($_POST['merchant_enabled'][$id]);
            $merchant['awinmid'] = $_POST['merchant_awinmid'][$id];
            $merchant['campaign'] = $_POST['merchant_campaign'][$id];
            $merchant['clickref'] = $_POST['merchant_clickref'][$id];
            $merchant['domains'] = array_filter(array_map('trim', explode("\n", $_POST['merchant_domains'][$id])));
        }

        // Add new merchant if provided
        if (!empty($_POST['new_merchant_name']) && !empty($_POST['new_merchant_awinmid'])) {
            $id = yourls_sanitize_title($_POST['new_merchant_name']);
            $domains = array_filter(array_map('trim', explode("\n", $_POST['new_merchant_domains'])));

            $this->settings['merchants'][$id] = [
                'name' => $_POST['new_merchant_name'],
                'awinmid' => $_POST['new_merchant_awinmid'],
                'campaign' => $_POST['new_merchant_campaign'],
                'clickref' => $_POST['new_merchant_clickref'],
                'domains' => $domains,
                'enabled' => true
            ];
        }

        // Save settings
        yourls_update_option(self::OPTION_NAME, $this->settings);
        yourls_add_notice('Settings updated successfully');
    }

    /**
     * Process URL for redirection
     * @param array $args Arguments from YOURLS
     */
    public function processUrl($args): void
    {
        try {
            $url = $args[0] ?? '';
            if (empty($url)) return;

            $merchant = $this->findMatchingMerchant($url);
            if ($merchant && $merchant['enabled']) {
                $this->handleRedirect($url, $merchant);
            }
        } catch (Exception $e) {
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
        if (!$parsedUrl || empty($parsedUrl['host'])) {
            return null;
        }

        $host = strtolower(preg_replace('/^www\./', '', $parsedUrl['host']));

        foreach ($this->settings['merchants'] as $merchant) {
            if (in_array($host, $merchant['domains'])) {
                return $merchant;
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
        $baseUrl = $this->buildBaseUrl($urlParts);

        // Get existing parameters
        $existingParams = [];
        if (isset($urlParts['query'])) {
            parse_str($urlParts['query'], $existingParams);
        }

        // Build Awin parameters
        $params = [
            'awinmid' => $merchant['awinmid'],
            'awinaffid' => $this->settings['awinaffid']
        ];

        // Add campaign if provided in URL or merchant settings
        if (isset($existingParams['campaign'])) {
            $params['campaign'] = $existingParams['campaign'];
        } elseif (!empty($merchant['campaign'])) {
            $params['campaign'] = $merchant['campaign'];
        }

        // Add clickrefs
        if (isset($existingParams['clickref'])) {
            $params['clickref'] = $existingParams['clickref'];
        } elseif (!empty($merchant['clickref'])) {
            $params['clickref'] = $merchant['clickref'];
        }

        // Add additional clickrefs if provided in URL
        for ($i = 2; $i <= self::CLICKREF_COUNT; $i++) {
            $key = "clickref{$i}";
            if (isset($existingParams[$key])) {
                $params[$key] = $existingParams[$key];
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
?>
