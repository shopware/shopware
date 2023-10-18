import { test as base, expect, Page } from '@playwright/test';
import { Actor } from '@fixtures/Actor';
import { components } from '@shopware/api-client/admin-api-types';
import { WorkerFixtures } from './WorkerFixtures';

export interface SetupFixtures {
    adminPage: Page;
    storefrontPage: Page;
    anonStorefrontPage: Page;
    salesChannelProduct: components['schemas']['Product'];
    product: components['schemas']['Product'];
    shopCustomer: Actor;
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

        expect(response.ok()).toBeTruthy();

        await page.goto('#/login');

        await page.getByLabel('Username').fill(adminUser.username);
        await page.getByLabel('Password').fill(adminUser.password);

        await page.getByRole('button', { name: 'Log in' }).click();

        // Wait until the page is loaded
        await expect(page.locator('css=.sw-admin-menu__header-logo').first()).toBeVisible({
            timeout: 10000,
        });

        await expect(page.locator('.sw-skeleton')).toHaveCount(0, {
            timeout: 10000,
        });

        await expect(page.locator('.sw-loader')).toHaveCount(0, {
            timeout: 10000,
        });

        // Run the test
        await use(page);

        await page.close();
        await context.close();

        // Cleanup created user
        const cleanupResponse = await adminApiContext.delete(`./user/${uuid}`);
        expect(cleanupResponse.ok()).toBeTruthy();
    },

    storefrontPage: async ({ defaultStorefront, browser }, use) => {
        const { customer, url } = defaultStorefront;

        const context = await browser.newContext({
            baseURL: url,
        });
        const page = await context.newPage();

        // Go to login page and login
        await page.goto('./account/login');

        await page.getByLabel('Your email address').type(customer.email);
        await page.getByLabel('Your password').type(customer.password);
        await page.getByRole('button', { name: 'Log in' }).click();
        await page.getByRole('heading', { name: 'Overview' }).isVisible();

        await page.goto('./');

        await use(page);

        await page.close();
        await context.close();
    },

    anonStorefrontPage: async ({ defaultStorefront, browser }, use) => {
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

    /**
     * Collection of all fixtures
     */
    product: async ({ idProvider, storeBaseConfig, adminApiContext }, use) => {
        // Generate unique IDs
        const { id: productId, uuid: productUuid } = idProvider.getIdPair();
        const productName = `Test_product_${productId}`;

        // Create product
        const newProduct = await adminApiContext.post<components['schemas']['Product']>('./product?_response', {
            data: {
                active: true,
                stock: 10,
                taxId: storeBaseConfig.taxId,
                id: productUuid,
                name: productName,
                productNumber: 'TEST-' + productId,
                price: [
                    {
                        // @ts-expect-error broken types
                        currencyId: storeBaseConfig.eurCurrencyId,
                        // @ts-expect-error broken types
                        gross: 10,
                        // @ts-expect-error broken types
                        linked: false,
                        // @ts-expect-error broken types
                        net: 8.4,
                    },
                ],
            },
        });

        expect(newProduct.ok()).toBeTruthy();

        // Allow access to new product in test
        const newProductValue = (await newProduct.json()) as { data: components['schemas']['Product'] };
        await use(newProductValue.data);

        // Delete product after the test is done
        await adminApiContext.delete(`./product/${productUuid}`);
    },

    salesChannelProduct: async ({ adminApiContext, defaultStorefront, product }, use) => {
        const syncResp = await adminApiContext.post('./_action/sync', {
            data: {
                'add product to sales channel': {
                    entity: 'product_visibility',
                    action: 'upsert',
                    payload: [
                        {
                            productId: product.id,
                            salesChannelId: defaultStorefront.salesChannel.id,
                            visibility: 30,
                        },
                    ],
                },
                'add product to root navigation': {
                    entity: 'product_category',
                    action: 'upsert',
                    payload: [
                        {
                            productId: product.id,
                            categoryId: defaultStorefront.salesChannel.navigationCategoryId,
                        },
                    ],
                },
            },
        });

        expect(syncResp.ok()).toBeTruthy();

        await use(product);
    },

    shopCustomer: async ({ storefrontPage }, use) => {
        const shopCustomer = new Actor('Shop customer', storefrontPage);

        await use(shopCustomer);
    },
});
