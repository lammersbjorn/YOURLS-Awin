import { test, expect } from '../utils/fixtures';

const ADMIN_PATH = '/admin/plugins.php?page=awin_affiliate_settings';

test.describe('Plugin basics', () => {
  test('YOURLS admin still loads with the plugin active', async ({ page, errors }) => {
    const response = await page.goto('/admin/index.php');
    expect(response?.status()).toBeLessThan(400);
    await expect(page.locator('#new_url_form')).toBeVisible();
    expect(errors.serverErrors).toEqual([]);
  });

  test('plugin appears as Active on plugins page', async ({ page }) => {
    await page.goto('/admin/plugins.php');
    await expect(
      page.locator('tr.plugin.active', { hasText: 'Awin Affiliate' })
    ).toBeVisible();
  });

  test('settings page renders the configuration form', async ({ page, errors }) => {
    await page.goto(ADMIN_PATH);
    await expect(page.locator('input[name="awinaffid"]')).toBeVisible();
    // The default Coolblue merchant should be visible after the first
    // activation seeds the option.
    await expect(page.locator('h4', { hasText: /Coolblue/i })).toBeVisible();
    await expect(page.locator('input[type="submit"][value*="Save"]')).toBeVisible();
    expect(errors.serverErrors).toEqual([]);
  });

  test('settings page heading is rendered', async ({ page, errors }) => {
    await page.goto(ADMIN_PATH);
    await expect(page.locator('h2', { hasText: /Awin Affiliate Settings/i })).toBeVisible();
    await expect(page.locator('h3', { hasText: /Add New Merchant/i })).toBeVisible();
    expect(errors.serverErrors).toEqual([]);
  });
});
