import { test as base, expect, Page } from '@playwright/test';
import { Actor } from '@fixtures/Actor';
import { WorkerFixtures } from './WorkerFixtures';

export interface SetupFixtures {
    adminPage: Page;
    storefrontPage: Page;
    shopCustomer: Actor;
    shopAdmin: Actor;
}

export const test = base.extend<SetupFixtures, WorkerFixtures>({
    adminPage: async ({ idProvider, adminApiContext, browser, storeBaseConfig }, use) => {
        const context = await browser.newContext({
            baseURL: storeBaseConfig.adminUrl,
        });
        const page = await context.newPage();

        const { id, uuid } = idProvider.getIdPair();

        const adminUser = {
            id: uuid,
            username: `admin_${id}`,
            firstName: `${id} admin`,
            lastName: `${id} admin`,
            localeId: storeBaseConfig.enGBLocaleId,
            email: `admin_${id}@example.com`,
            timezone: 'Europe/Berlin',
            password: 'shopware',
            admin: true,
        };

        const response = await adminApiContext.post('./user', {
            data: adminUser,
        });

        await expect(response.ok()).toBeTruthy();

        await page.goto('#/login');

        await page.getByLabel('Username').fill(adminUser.username);
        await page.getByLabel('Password').fill(adminUser.password);

        await page.getByRole('button', { name: 'Log in' }).click();

        // Wait until the page is loaded
        await expect(page.locator('css=.sw-admin-menu__header-logo').first()).toBeVisible({
            timeout: 20000,
        });

        await expect(page.locator('.sw-skeleton')).toHaveCount(0, {
            timeout: 10000,
        });

        await expect(page.locator('.sw-loader')).toHaveCount(0, {
            timeout: 10000,
        });

        await page.addStyleTag({
            path: 'resources/customAdmin.css',
        });

        // Run the test
        await use(page);

        await page.close();
        await context.close();

        // Cleanup created user
        const cleanupResponse = await adminApiContext.delete(`./user/${uuid}`);
        await expect(cleanupResponse.ok()).toBeTruthy();
    },

    storefrontPage: async ({ defaultStorefront, browser }, use) => {
        const { url } = defaultStorefront;

        const context = await browser.newContext({
            baseURL: url,
        });
        const page = await context.newPage();

        await page.goto('./');

        await use(page);

        await page.close();
        await context.close();
    },

    shopCustomer: async ({ storefrontPage }, use) => {
        const shopCustomer = new Actor('Shop customer', storefrontPage);

        await use(shopCustomer);
    },

    shopAdmin: async ({ adminPage }, use) => {
        const shopAdmin = new Actor('Shop administrator', adminPage);

        await use(shopAdmin);
    },

});
