import {expect, test as setup} from './../../fixtures/acceptance-test';

const authFile = 'playwright/.auth/admin.json';

setup('Authenticate with admin API', async ({ adminApiContext }) => {
    const authData = await adminApiContext.get('./_info/config', {});

    expect(authData.ok()).toBeTruthy();
});

setup('Authenticate via admin user login', async ({ page, loginPage }) => {
    await loginPage.goto();
    await loginPage.login();

    await page.waitForURL('/admin#/sw/dashboard/index');

    await expect(page.locator('h1.sw-dashboard-index__welcome-title')).toBeVisible();

    await page.context().storageState({ path: authFile });
});

