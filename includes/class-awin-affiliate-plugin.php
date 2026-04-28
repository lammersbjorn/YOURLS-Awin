<?php

declare(strict_types=1);

if (!defined('YOURLS_ABSPATH')) {
    die();
}

class AwinAffiliatePlugin
{
    private const AWIN_BASE_URL = 'https://www.awin1.com/cread.php';
    private const CLICKREF_COUNT = 6;
    private const OPTION_NAME = 'awin_affiliate_settings';

    private array $settings = [];

    public function __construct()
    {
        yourls_add_action('plugins_loaded', [$this, 'init']);
        yourls_add_action('plugins_loaded', [$this, 'addAdminPage']);
        yourls_add_action('admin_page_awin_affiliate_settings', [$this, 'displayAdminPage']);
        yourls_add_action('admin_init', [$this, 'processFormSubmission']);
        yourls_add_action('pre_redirect', [$this, 'processUrl'], 10);
    }

    public function init(): void
    {
        $stored = yourls_get_option(self::OPTION_NAME);

        if (!is_array($stored)) {
            $this->settings = $this->defaultSettings();
            yourls_add_option(self::OPTION_NAME, $this->settings);
            return;
        }

        $stored += [
            'awinaffid' => '',
            'default_campaign' => '',
            'default_clickref' => '',
            'merchants' => [],
        ];

        if (!is_array($stored['merchants'])) {
            $stored['merchants'] = [];
        }

        $this->settings = $stored;
    }

    public function addAdminPage(): void
    {
        yourls_register_plugin_page('awin_affiliate_settings', 'Awin Affiliate Settings', [$this, 'displayAdminPage']);
    }

    public function displayAdminPage(): void
    {
        if (!yourls_is_admin()) {
            die('Access denied');
        }

        $nonce = yourls_create_nonce('awin_affiliate_settings');
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
                        $idAttr = yourls_esc_attr((string) $id);
                        $name = (string) ($merchant['name'] ?? $id);
                        $awinmid = (string) ($merchant['awinmid'] ?? '');
                        $campaign = (string) ($merchant['campaign'] ?? '');
                        $clickref = (string) ($merchant['clickref'] ?? '');
                        $enabled = !empty($merchant['enabled']);
                        $domains = is_array($merchant['domains'] ?? null)
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
                    <p>No merchants configured yet - fill in form below to add one.</p>
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

            <p><input type="submit" value="Save Settings" class="button primary"></p>
        </form>
        </div><!-- .awin-page -->

        <style>
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

    public function processFormSubmission(): void
    {
        if (!isset($_POST['action']) || $_POST['action'] !== 'update_awin_settings') {
            return;
        }

        if (!yourls_is_admin()) {
            return;
        }

        yourls_verify_nonce('awin_affiliate_settings');

        $this->settings['awinaffid'] = trim((string) ($_POST['awinaffid'] ?? ''));

        foreach ($this->settings['merchants'] as $id => &$merchant) {
            $merchant['enabled'] = isset($_POST['merchant_enabled'][$id]);
            $merchant['awinmid'] = trim((string) ($_POST['merchant_awinmid'][$id] ?? ''));
            $merchant['campaign'] = trim((string) ($_POST['merchant_campaign'][$id] ?? ''));
            $merchant['clickref'] = trim((string) ($_POST['merchant_clickref'][$id] ?? ''));
            $merchant['domains'] = $this->normaliseDomainList(
                (string) ($_POST['merchant_domains'][$id] ?? '')
            );
        }
        unset($merchant);

        $newName = trim((string) ($_POST['new_merchant_name'] ?? ''));
        $newAwinmid = trim((string) ($_POST['new_merchant_awinmid'] ?? ''));
        if ($newName !== '' && $newAwinmid !== '') {
            $id = yourls_sanitize_title($newName);
            if ($id !== '' && !isset($this->settings['merchants'][$id])) {
                $this->settings['merchants'][$id] = [
                    'name' => $newName,
                    'awinmid' => $newAwinmid,
                    'campaign' => trim((string) ($_POST['new_merchant_campaign'] ?? '')),
                    'clickref' => trim((string) ($_POST['new_merchant_clickref'] ?? '')),
                    'domains' => $this->normaliseDomainList(
                        (string) ($_POST['new_merchant_domains'] ?? '')
                    ),
                    'enabled' => true,
                ];
            }
        }

        yourls_update_option(self::OPTION_NAME, $this->settings);
        yourls_add_notice('Settings updated successfully');
    }

    public function processUrl($location): void
    {
        try {
            $url = $location;
            if (is_array($location)) {
                $url = $location[0] ?? '';
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

    private function defaultSettings(): array
    {
        return [
            'awinaffid' => '',
            'default_campaign' => '',
            'default_clickref' => '',
            'merchants' => [
                'coolblue' => [
                    'name' => 'Coolblue',
                    'awinmid' => '',
                    'domains' => [
                        'coolblue.nl',
                        'coolblue.be',
                        'coolblue.de',
                        'coolblue.fr',
                    ],
                    'enabled' => true,
                ],
            ],
        ];
    }

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

    private function findMatchingMerchant(string $url): ?array
    {
        $parsedUrl = parse_url($url);
        if (!is_array($parsedUrl) || empty($parsedUrl['host'])) {
            return null;
        }

        $host = strtolower((string) $parsedUrl['host']);
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

    private function handleRedirect(string $url, array $merchant): void
    {
        $urlParts = parse_url($url);
        if (!is_array($urlParts) || empty($urlParts['host'])) {
            return;
        }

        $baseUrl = $this->buildBaseUrl($urlParts);

        $existingParams = [];
        if (isset($urlParts['query']) && is_string($urlParts['query'])) {
            parse_str($urlParts['query'], $existingParams);
        }

        $params = [
            'awinmid' => (string) ($merchant['awinmid'] ?? ''),
            'awinaffid' => (string) ($this->settings['awinaffid'] ?? ''),
        ];

        $campaign = $this->normaliseQueryParamValue($existingParams, 'campaign');
        if ($campaign !== null) {
            $params['campaign'] = $campaign;
        } elseif (!empty($merchant['campaign'])) {
            $params['campaign'] = (string) $merchant['campaign'];
        }

        $clickref = $this->normaliseQueryParamValue($existingParams, 'clickref');
        if ($clickref !== null) {
            $params['clickref'] = $clickref;
        } elseif (!empty($merchant['clickref'])) {
            $params['clickref'] = (string) $merchant['clickref'];
        }

        for ($i = 2; $i <= self::CLICKREF_COUNT; $i++) {
            $key = "clickref{$i}";
            $value = $this->normaliseQueryParamValue($existingParams, $key);
            if ($value !== null) {
                $params[$key] = $value;
            }
        }

        $this->outputRedirectPage($baseUrl, $params);
        die();
    }

    private function buildBaseUrl(array $urlParts): string
    {
        $scheme = $urlParts['scheme'] ?? 'https';
        $host = (string) ($urlParts['host'] ?? '');
        $port = isset($urlParts['port']) ? ':' . (string) $urlParts['port'] : '';
        $path = (string) ($urlParts['path'] ?? '');
        $query = isset($urlParts['query']) ? '?' . (string) $urlParts['query'] : '';
        $fragment = isset($urlParts['fragment']) ? '#' . (string) $urlParts['fragment'] : '';

        return sprintf('%s://%s%s%s%s', $scheme, $host . $port, $path, $query, $fragment);
    }

    private function normaliseQueryParamValue(array $queryParams, string $key): ?string
    {
        if (!array_key_exists($key, $queryParams)) {
            return null;
        }

        $value = $queryParams[$key];
        if (is_array($value) || (!is_scalar($value) && $value !== null)) {
            return null;
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    private function outputRedirectPage(string $url, array $params): void
    {
        $queryParams = array_map(
            static fn($key, $value) => $key . '=' . urlencode($value),
            array_keys($params),
            array_values($params)
        );

        $queryParams[] = 'ued=' . urlencode($url);
        $finalUrl = self::AWIN_BASE_URL . '?' . implode('&', $queryParams);

        header('Content-Type: text/html; charset=utf-8');
        header('X-Robots-Tag: noindex, nofollow');

        echo $this->getRedirectTemplate($finalUrl);
    }

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
