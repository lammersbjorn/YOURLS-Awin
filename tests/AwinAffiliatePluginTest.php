<?php

declare(strict_types=1);

final class AwinAffiliatePluginTest extends PHPUnit\Framework\TestCase
{
    private const OPTION_NAME = 'awin_affiliate_settings';

    public function test_plugin_class_is_loaded(): void
    {
        $this->assertTrue(class_exists('AwinAffiliatePlugin'));
    }

    public function test_plugin_bootstrap_loads_runtime_class_file(): void
    {
        $pluginFile = dirname(__DIR__) . '/plugin.php';
        $bootstrap = file_get_contents($pluginFile);

        $this->assertIsString($bootstrap);
        $this->assertStringContainsString("require_once __DIR__ . '/includes/class-awin-affiliate-plugin.php';", $bootstrap);
        $this->assertStringNotContainsString('class AwinAffiliatePlugin', $bootstrap);
    }

    /**
     * @dataProvider registeredHooksProvider
     */
    public function test_plugin_registers_expected_hooks(string $hook, string $method): void
    {
        $this->assertHookHasPluginMethod($hook, $method);
    }

    public static function registeredHooksProvider(): array
    {
        return [
            'init' => ['plugins_loaded', 'init'],
            'admin page registration' => ['plugins_loaded', 'addAdminPage'],
            'admin page render' => ['admin_page_awin_affiliate_settings', 'displayAdminPage'],
            'form submission' => ['admin_init', 'processFormSubmission'],
            'redirect hook' => ['pre_redirect', 'processUrl'],
        ];
    }

    public function test_init_provides_expected_settings_shape(): void
    {
        $plugin = $this->pluginInstance();
        $plugin->init();

        $settings = $this->readPrivateProperty($plugin, 'settings');

        $this->assertIsArray($settings);
        $this->assertArrayHasKey('awinaffid', $settings);
        $this->assertArrayHasKey('default_campaign', $settings);
        $this->assertArrayHasKey('default_clickref', $settings);
        $this->assertArrayHasKey('merchants', $settings);
        $this->assertIsArray($settings['merchants']);
    }

    public function test_default_coolblue_merchant_is_seeded_on_fresh_install(): void
    {
        $this->pluginInstance()->init();
        $settings = yourls_get_option(self::OPTION_NAME);

        $this->assertIsArray($settings);
        $this->assertArrayHasKey('coolblue', $settings['merchants']);
        $this->assertSame('Coolblue', $settings['merchants']['coolblue']['name']);
        $this->assertContains('coolblue.nl', $settings['merchants']['coolblue']['domains']);
    }

    public function test_existing_option_name_is_preserved_for_compatibility(): void
    {
        yourls_update_option(self::OPTION_NAME, ['merchants' => []]);
        $this->pluginInstance()->init();
        $settings = yourls_get_option(self::OPTION_NAME);

        $this->assertIsArray($settings);
        $this->assertArrayHasKey('merchants', $settings);
    }

    public function test_process_url_accepts_yourls_action_args_array_without_output(): void
    {
        $plugin = $this->pluginInstance();
        $originalSettings = $this->readPrivateProperty($plugin, 'settings');
        $bufferLevel = ob_get_level();

        try {
            $this->writePrivateProperty($plugin, 'settings', [
                'awinaffid' => '12345',
                'default_campaign' => '',
                'default_clickref' => '',
                'merchants' => [
                    'coolblue' => [
                        'name' => 'Coolblue',
                        'awinmid' => '999',
                        'domains' => ['coolblue.nl'],
                        'enabled' => false,
                    ],
                ],
            ]);

            ob_start();
            $plugin->processUrl(['https://www.coolblue.nl/product?campaign[]=bad', 301]);
            $plugin->processUrl('https://example.invalid/product');
            $output = ob_get_clean();
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            $this->writePrivateProperty($plugin, 'settings', $originalSettings);
        }

        $this->assertSame('', $output);
    }

    /**
     * @dataProvider queryValueProvider
     */
    public function test_query_param_values_are_normalised_without_array_to_string_warnings(array $params, string $key, ?string $expected): void
    {
        $method = new ReflectionMethod('AwinAffiliatePlugin', 'normaliseQueryParamValue');
        $method->setAccessible(true);

        $this->assertSame($expected, $method->invoke($this->pluginInstance(), $params, $key));
    }

    public static function queryValueProvider(): array
    {
        return [
            'missing' => [[], 'campaign', null],
            'string' => [['campaign' => 'spring'], 'campaign', 'spring'],
            'integer' => [['clickref' => 123], 'clickref', '123'],
            'empty string' => [['clickref2' => ''], 'clickref2', ''],
            'array from bracket syntax' => [['campaign' => ['bad']], 'campaign', null],
        ];
    }

    private function assertHookHasPluginMethod(string $hook, string $method): void
    {
        $filters = yourls_get_filters($hook);
        $this->assertIsArray($filters, "Hook {$hook} is not registered.");

        foreach ($filters as $priorityBucket) {
            if (!is_array($priorityBucket)) {
                continue;
            }
            foreach ($priorityBucket as $entry) {
                $callback = $entry['function'] ?? null;
                if (is_array($callback) && ($callback[0] ?? null) instanceof AwinAffiliatePlugin && ($callback[1] ?? null) === $method) {
                    $this->assertSame(1, $entry['accepted_args'] ?? null);
                    return;
                }
            }
        }

        $this->fail("AwinAffiliatePlugin::{$method} is not registered on {$hook}.");
    }

    private function pluginInstance(): AwinAffiliatePlugin
    {
        $filters = yourls_get_filters('pre_redirect');
        $this->assertIsArray($filters);

        foreach ($filters as $priorityBucket) {
            if (!is_array($priorityBucket)) {
                continue;
            }
            foreach ($priorityBucket as $entry) {
                $callback = $entry['function'] ?? null;
                if (is_array($callback) && ($callback[0] ?? null) instanceof AwinAffiliatePlugin) {
                    return $callback[0];
                }
            }
        }

        $this->fail('AwinAffiliatePlugin instance was not found in YOURLS hooks.');
    }

    private function readPrivateProperty(AwinAffiliatePlugin $plugin, string $property)
    {
        $reflectionProperty = new ReflectionProperty($plugin, $property);
        $reflectionProperty->setAccessible(true);

        return $reflectionProperty->getValue($plugin);
    }

    private function writePrivateProperty(AwinAffiliatePlugin $plugin, string $property, $value): void
    {
        $reflectionProperty = new ReflectionProperty($plugin, $property);
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($plugin, $value);
    }
}
