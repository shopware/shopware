import { test, expect } from '@fixtures/AcceptanceTest';

test('@install: Install a fresh shop', async ({ page }) => {
    await page.goto(process.env.APP_URL);

    await page.getByRole('link', { name: 'Next' }).click();
    await page.getByRole('button', { name: 'Next' }).click();
    await page.getByText('I agree to the General Terms and Conditions of Business (GTC)').click();
    await page.getByRole('button', { name: 'Next' }).click();

    await page.getByLabel('Server:').fill('database');
    await page.getByLabel('User:').fill('root');
    await page.getByLabel('Password:').fill('app');

    await page.getByText('New database:').click();
    await page.locator('#databaseName_new').fill('install_test');

    await page.getByRole('button', { name: 'Start installation' }).click();

    await test.slow();

    await expect(page.locator('#import-finished:visible div').first())
        .toHaveText('Shopware 6 has been installed!', { timeout: 120000 });
    await page.getByRole('link', { name: 'Next' }).click();

    await page.getByLabel('Shop name').fill('Basic install test');

    await page.locator('#shop-configuration div').filter({ hasText: 'Almost done. You just need to make some few basic settings in your shop, Shopwar' }).click();

    await page.getByLabel('Shop email address:').fill('mustermann@example.com');
    await page.locator('label').filter({ hasText: 'Pound sterling (UK)' }).click();

    await page.getByLabel('Admin email:').fill('admin@example.com');

    await page.getByLabel('Admin first name:').fill('Admin');
    await page.getByLabel('Admin last name:').fill('Admin');
    await page.getByLabel('Admin login name:').fill('admin');
    await page.getByLabel('Admin password:').fill('shopware');

    await page.getByRole('button', { name: 'Next' }).click();

    // test admin login

    // Wait until the page is loaded
    await expect(page.locator('css=.sw-admin-menu__header-logo').first()).toBeVisible({
        timeout: 20000,
    });
});