/* eslint-disable playwright/no-conditional-in-test */
import { test, expect } from '@fixtures/AcceptanceTest';

test.skip('Update an existing Shopware instance.', { tag: '@Update' }, async ({
    page,
    AdminApiContext,
}) => {
    test.slow();

    await page.goto(process.env.ADMIN_URL);

    await page.getByPlaceholder('Enter your username...').fill('admin');
    await page.getByPlaceholder('Enter your password...').fill('shopware');
    await page.getByPlaceholder('Enter your password...').press('Enter');

    await expect(page.locator('css=.sw-admin-menu__header-logo').first()).toBeVisible({
        timeout: 20000,
    });

    await ((await AdminApiContext.get(`./_info/config`)).json()) as { version: string };

    await page.getByRole('button', { name: 'Open update' }).click();

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

    await expect(page.getByText('6.6.9999999.9999999 Developer Version')).toBeVisible({
        timeout: 60000,
    });

    // test admin login
    // Wait until the page is loaded
    await expect(page.locator('css=.sw-admin-menu__header-logo').first()).toBeVisible({
        timeout: 60000,
    });
});
