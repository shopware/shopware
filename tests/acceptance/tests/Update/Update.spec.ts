import { test, expect } from '@fixtures/AcceptanceTest';

test(`Update an existing Shopware ${process.env.SHOPWARE_UPDATE_FROM} instance.`, { tag: '@Update' }, async ({
    AdminPage,
    AdminApiContext,
}) => {
    test.slow();

    await AdminPage.goto(process.env.ADMIN_URL);

    await expect(AdminPage.locator('css=.sw-admin-menu__header-logo').first()).toBeVisible({
        timeout: 20000,
    });

    await ((await AdminApiContext.get(`./_info/config`)).json()) as { version: string };

    await AdminPage.goto('#/sw/settings/shopware/updates/wizard');

    await AdminPage.getByRole('button', { name: 'Start update' }).click();

    await AdminPage.getByLabel('Yes, I\'ve created a backup.').check();
    await AdminPage.getByRole('button', { name: 'Install' }).click();
    await AdminPage.waitForLoadState('domcontentloaded')

    await AdminPage.getByRole('link', { name: 'Continue' }).click();
    await AdminPage.waitForLoadState('domcontentloaded')

    await AdminPage.getByRole('button', { name: 'Continue' }).click();
    await AdminPage.waitForLoadState('domcontentloaded');

    await AdminPage.getByRole('button', { name: 'Update Shopware' }).click();

    const response = await AdminPage.waitForResponse((response) => response.url().includes('/update/_finish'), { timeout: 120000 });
    expect(response.status()).toBe(200);

    await AdminPage.screenshot();

    await expect(AdminPage.getByRole('heading', { name: 'Finish' })).toBeVisible({ timeout: 120000 });

    await AdminPage.getByRole('button', { name: 'Open Administration' }).click();

    const versionResponse = await AdminApiContext.get('./_info/config');
    expect(versionResponse.ok(), '/_info/config request failed').toBeTruthy();
    const config = (await versionResponse.json()) as { version: string };

    await expect(AdminPage.locator('css=.sw-version__info').first()).toContainText(`${config.version}`, {
        timeout: 60000,
    });

    // test admin login
    // Wait until the page is loaded
    await expect(AdminPage.locator('css=.sw-admin-menu__header-logo').first()).toBeVisible({
        timeout: 60000,
    });
});
