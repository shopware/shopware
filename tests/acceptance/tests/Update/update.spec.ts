/* eslint-disable playwright/no-conditional-in-test */
import { test, expect } from '@fixtures/AcceptanceTest';

test('@update: Update a shop', async ({ page, adminApiContext }) => {
    await test.slow();

    await page.goto(process.env.ADMIN_URL);

    await page.getByPlaceholder('Enter your username...').fill('admin');
    await page.getByPlaceholder('Enter your password...').fill('shopware');
    await page.getByPlaceholder('Enter your password...').press('Enter');

    await expect(page.locator('css=.sw-admin-menu__header-logo').first()).toBeVisible({
        timeout: 20000,
    });

    const config = await ((await adminApiContext.get(`./_info/config`)).json()) as { version: string };

    // hack for the RCs, can be removed after NEXT-34218 is fixed in the final is released
    if (config.version.match(/6.6.0.0-RC[1-4]/)) {
        await page.goto(`${process.env.ADMIN_URL}/#/sw/settings/shopware/updates/wizard`);
    } else {
        await page.getByRole('button', { name: 'Open update' }).click();
    }

    await page.getByRole('button', { name: 'Start update' }).click();

    await page.getByLabel('Yes, I\'ve created a backup.').check();
    await page.getByRole('button', { name: 'Install' }).click();
    await page.waitForLoadState('domcontentloaded')

    await page.getByRole('link', { name: 'Continue' }).click();
    await page.waitForLoadState('domcontentloaded')

    await page.getByRole('button', { name: 'Save configuration' }).click();
    await page.waitForLoadState('domcontentloaded');

    await page.getByRole('button', { name: 'Update Shopware' }).click();

    const response = await page.waitForResponse((response) => response.url().includes('/update/_finish'), { timeout: 120000 });
    expect(response.status()).toBe(200);

    await page.screenshot();

    await expect(page.getByRole('heading', { name: 'Finish' })).toBeVisible({ timeout: 120000 });

    await page.getByRole('button', { name: 'Open Administration' }).click();

    await expect(page.getByText('6.6.9999999.9999999 Developer Version')).toBeVisible();

    // test admin login

    // Wait until the page is loaded
    await expect(page.locator('css=.sw-admin-menu__header-logo').first()).toBeVisible({
        timeout: 60000,
    });
});