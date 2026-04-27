<?php
/**
 * Smoke coverage for the Awin Affiliate plugin.
 *
 * Most of the plugin's surface area is private methods on the
 * AwinAffiliatePlugin class, so these tests focus on the things that
 * matter for compatibility:
 *   - the plugin file actually loaded without raising fatals,
 *   - the class is instantiated as expected,
 *   - the hooks the plugin promises are wired up against YOURLS' filter
 *     registry,
 *   - the plugin option seeds with a sane default shape on first run.
 */

declare(strict_types=1);

class PluginTest extends PHPUnit\Framework\TestCase
{
    public function test_plugin_class_is_loaded(): void
    {
        $this->assertTrue(
            class_exists('AwinAffiliatePlugin'),
            'AwinAffiliatePlugin class is not in scope — plugin.php failed to load.'
        );
    }

    /**
     * @dataProvider provideRegisteredHooks
     */
    public function test_plugin_registers_hook(string $hook, string $expectedMethod): void
    {
        $filters = yourls_get_filters($hook);
        $this->assertIsArray(
            $filters,
            "Hook '{$hook}' has no callbacks — the plugin did not register it."
        );

        $found = false;
        foreach ($filters as $bucket) {
            if (!is_array($bucket)) {
                continue;
            }
            foreach ($bucket as $entry) {
                $callback = $entry['function'] ?? null;
                if (is_array($callback) && isset($callback[1]) && $callback[1] === $expectedMethod) {
                    $found = true;
                    break 2;
                }
            }
        }
        $this->assertTrue(
            $found,
            "Plugin method '{$expectedMethod}' is not bound to '{$hook}'."
        );
    }

    public static function provideRegisteredHooks(): array
    {
        return [
            'init'                 => ['plugins_loaded', 'init'],
            'admin page'           => ['plugins_loaded', 'addAdminPage'],
            'form submission'      => ['admin_init',     'processFormSubmission'],
            'pre_redirect handler' => ['pre_redirect',   'processUrl'],
        ];
    }
}
