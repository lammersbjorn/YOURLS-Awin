import { test, expect } from '../utils/fixtures';

const ADMIN_PATH = '/admin/plugins.php?page=awin_affiliate_settings';

test.describe.configure({ mode: 'serial' });

test.describe('Saving settings via the admin form', () => {
  const stamp = Date.now().toString(36);
  const affid = `AF${stamp}`;
  const merchantName = `Awin TestShop ${stamp}`;
  const merchantAwinmid = `MID${stamp}`;
  const merchantDomain = `awin-testshop-${stamp}.example.com`;

  test('updating the global Awin Affiliate ID persists across reload', async ({ page, errors }) => {
    await page.goto(ADMIN_PATH);

    await page.locator('input[name="awinaffid"]').fill(affid);
    await page.locator('input[type="submit"][value*="Save"]').first().click();
    await page.waitForLoadState('networkidle');

    // Reload — the field must still hold the new value.
    await page.goto(ADMIN_PATH);
    await expect(page.locator('input[name="awinaffid"]')).toHaveValue(affid);

    expect(errors.serverErrors).toEqual([]);
  });

  test('a brand-new merchant can be added via the form', async ({ page, errors }) => {
    await page.goto(ADMIN_PATH);

    await page.locator('input[name="new_merchant_name"]').fill(merchantName);
    await page.locator('input[name="new_merchant_awinmid"]').fill(merchantAwinmid);
    await page.locator('textarea[name="new_merchant_domains"]').fill(merchantDomain);

    await page.locator('input[type="submit"][value*="Save"]').first().click();
    await page.waitForLoadState('networkidle');

    // The new merchant should appear as a `merchant-settings` card with a
    // header showing its name.
    await expect(
      page.locator('.merchant-settings', { hasText: merchantName })
    ).toBeVisible();

    // After reload the data must still be there.
    await page.goto(ADMIN_PATH);
    await expect(
      page.locator('.merchant-settings', { hasText: merchantName })
    ).toBeVisible();
    // The textarea for the new merchant should contain the configured domain.
    await expect(
      page.locator('.merchant-settings', { hasText: merchantName })
        .locator('textarea[name^="merchant_domains"]')
    ).toContainText(merchantDomain);

    expect(errors.serverErrors).toEqual([]);
  });

  test('toggling a merchant\'s enabled checkbox round-trips', async ({ page, errors }) => {
    await page.goto(ADMIN_PATH);

    // Find the new merchant's enabled checkbox and disable it.
    const merchantBlock = page.locator('.merchant-settings', { hasText: merchantName });
    const enabledCheckbox = merchantBlock.locator(
      'input[type="checkbox"][name^="merchant_enabled"]'
    );
    await expect(enabledCheckbox).toBeChecked();
    await enabledCheckbox.uncheck();

    await page.locator('input[type="submit"][value*="Save"]').first().click();
    await page.waitForLoadState('networkidle');

    // Reload — the checkbox should now be unchecked.
    await page.goto(ADMIN_PATH);
    const refetched = page
      .locator('.merchant-settings', { hasText: merchantName })
      .locator('input[type="checkbox"][name^="merchant_enabled"]');
    await expect(refetched).not.toBeChecked();

    expect(errors.serverErrors).toEqual([]);
  });
});
