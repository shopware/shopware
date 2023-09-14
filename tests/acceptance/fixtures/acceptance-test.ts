import {test as base, request, APIRequestContext, expect, Page} from '@playwright/test';
import { AdminApiContext } from "./AdminApiContext";
import { IdProvider } from "./IdProvider";
import {
    getCountryId,
    getCurrencyId,
    getDefaultShippingMethod,
    getLanguageData,
    getPaymentMethodId, getSnippetSetId, getTaxId,
    getThemeId
} from './sales-channel-helper';
import { components } from "@shopware/api-client/admin-api-types";
import crypto from "crypto";

interface StoreBaseConfig {
    storefrontTypeId: string;
    enGBLocaleId: string,
    enGBLanguageId: string,
    eurCurrencyId: string,
    invoicePaymentMethodId: string,
    defaultShippingMethod: string,
    taxId: string,
    deCountryId: string,
    enGBSnippetSetId: string,
    defaultThemeId: string,
    appUrl: string,
    adminUrl: string,
}

interface Cleanup {
    addCleanup: (cleanupMethod: (...params: any) => any) => void;
}

type TestFixtures = {
    cleanup: Cleanup,
    adminPage: Page,
    product: components['schemas']['Product'],
    storefrontPage: Page,
    anonStorefrontPage: Page,
    salesChannelProduct: components['schemas']['Product'],
}

type WorkerFixtures = {
    idProvider: IdProvider,
    defaultStorefront: {
        salesChannel: components['schemas']['SalesChannel'],
        customer: components['schemas']['Customer'] & { password: string },
        url: string,
    },
    adminApiContext: AdminApiContext,
    storeBaseConfig: StoreBaseConfig,

}

export * from '@playwright/test';

export const test = base.extend<TestFixtures, WorkerFixtures>({
    idProvider: [async ({ browser }, use, workerInfo) => {
        const idProvider = new IdProvider(workerInfo.workerIndex, process.env.SHOPWARE_ACCESS_KEY_ID);

        await use(idProvider);
    }, { scope: 'worker' }],

    cleanup: async ({}, use) => {
        const cleanupList = [];
        const addCleanup = (cleanupMethod: (...params: any) => any) => {
            cleanupList.push(cleanupMethod);
        };

        await use({ addCleanup });

        // iterate in reverse order to cleanup in the correct order
        for (let i = cleanupList.length - 1; i >= 0; i--) {
            await cleanupList[i]();
        }
    },

    adminApiContext: [async ({}, use) => {
        const adminApiContext = await AdminApiContext.newContext();
        await use(adminApiContext);
    }, { scope: 'worker' }],

    storeBaseConfig: [async ({ adminApiContext }, use) => {
        const requests = {
            language: getLanguageData('en-GB', adminApiContext),
            eurCurrencyId: getCurrencyId(adminApiContext),
            invoicePaymentMethodId: getPaymentMethodId('Shopware\\Core\\Checkout\\Payment\\Cart\\PaymentHandler\\InvoicePayment', adminApiContext),
            defaultShippingMethod: getDefaultShippingMethod(adminApiContext),
            getTaxId: getTaxId(adminApiContext),

            deCountryId: getCountryId('de', adminApiContext),
            enGBSnippetSetId: getSnippetSetId('en-GB', adminApiContext),

            defaultThemeId: getThemeId('Storefront', adminApiContext),
        };
        await Promise.all(Object.values(requests));

        const lang = await requests.language;
        await use({
            enGBLocaleId: lang.localeId,
            enGBLanguageId: lang.id,
            storefrontTypeId: '8a243080f92e4c719546314b577cf82b',
            eurCurrencyId: await requests.eurCurrencyId,
            invoicePaymentMethodId: await requests.invoicePaymentMethodId,
            defaultShippingMethod: await requests.defaultShippingMethod,
            taxId: await requests.getTaxId,

            deCountryId: await requests.deCountryId,
            enGBSnippetSetId: await requests.enGBSnippetSetId,

            defaultThemeId: await requests.defaultThemeId,

            appUrl: process.env['APP_URL'],
            adminUrl: process.env['ADMIN_URL'] || `${process.env['APP_URL']}admin/`,
        })
    }, { scope: 'worker' }],

    adminPage: async ({ idProvider, adminApiContext, browser, storeBaseConfig }, use) => {
        const context = await browser.newContext({
            baseURL: storeBaseConfig.adminUrl
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
        }

        const response = await adminApiContext.post('./user', {
            data: adminUser
        });

        expect(response.ok()).toBeTruthy();

        await page.goto('#/login');

        await page.getByLabel('Username').fill(adminUser.username);
        await page.getByLabel('Password').fill(adminUser.password);

        await page.getByRole('button', { name: 'Log in' }).click();

        // // Wait until the page is loaded
        // await expect(page.locator('css=.sw-admin-menu__header-logo').first()).toBeVisible({
        //     timeout: 10000,
        // });
        //
        // await expect(page.locator('.sw-skeleton')).toHaveCount(0, {
        //     timeout: 10000,
        // });
        //
        // await expect(page.locator('.sw-loader')).toHaveCount(0, {
        //     timeout: 10000,
        // });

        // Run the test
        await use(page);

        await page.close();
        await context.close();

        // Cleanup created user
        const cleanupResponse = await adminApiContext.delete(`./user/${uuid}`);
        expect(cleanupResponse.ok()).toBeTruthy();
    },

    defaultStorefront: [async ({ idProvider, adminApiContext, browser, storeBaseConfig }, use) => {
        const { id, uuid } = idProvider.getWorkerDerivedStableId('salesChannel');

        const { uuid: rootCategoryUuid } = idProvider.getWorkerDerivedStableId('category');
        const { uuid: customerGroupUuid } = idProvider.getWorkerDerivedStableId('customerGroup');
        const { uuid: domainUuid } = idProvider.getWorkerDerivedStableId('domain');
        const { uuid: customerUuid } = idProvider.getWorkerDerivedStableId('customer');

        const baseUrl =`${storeBaseConfig.appUrl}test-${uuid}/`;

        const currentConfigResponse = await adminApiContext.get(`./_action/system-config?domain=storefront&salesChannelId=${uuid}`);
        const currentConfig = await currentConfigResponse.json();

        await adminApiContext.delete(`./customer/${customerUuid}`);
        await adminApiContext.delete(`./sales-channel/${uuid}`);

        const syncResp = await adminApiContext.post('./_action/sync', {
            data: {
                'write-sales-channel': {
                    entity: 'sales_channel',
                    action: 'upsert',
                    payload: [{
                        id: uuid,
                        name: `${id} acceptance test`,
                        typeId: storeBaseConfig.storefrontTypeId,
                        languageId: storeBaseConfig.enGBLanguageId,

                        currencyId: storeBaseConfig.eurCurrencyId,
                        paymentMethodId: storeBaseConfig.invoicePaymentMethodId,
                        shippingMethodId: storeBaseConfig.defaultShippingMethod,
                        countryId: storeBaseConfig.deCountryId,

                        accessKey: 'AC' + uuid,

                        homeEnabled: true,

                        navigationCategory: {
                            id: rootCategoryUuid,
                            name: `${id} Acceptance test`,
                            displayNestedProducts: true,
                            type: 'page',
                            productAssignmentType: 'product'
                        },

                        domains: [
                            {
                                id: domainUuid,
                                url: baseUrl,
                                languageId: storeBaseConfig.enGBLanguageId,
                                snippetSetId: storeBaseConfig.enGBSnippetSetId,
                                currencyId: storeBaseConfig.eurCurrencyId,
                            }
                        ],

                        customerGroup: {
                            id: customerGroupUuid,
                            name: `${id} Acceptance test`
                        },

                        languages: [
                            { id: storeBaseConfig.enGBLanguageId }
                        ],
                        countries: [
                            { id: storeBaseConfig.deCountryId }
                        ],
                        shippingMethods: [
                            { id: storeBaseConfig.defaultShippingMethod }
                        ],
                        paymentMethods: [
                            { id: storeBaseConfig.invoicePaymentMethodId }
                        ],
                        currencies: [
                            { id: storeBaseConfig.eurCurrencyId }
                        ],
                    }]
                },
                // 'set-theme-seed': {
                //     entity: 'system_settings',
                //     action: 'upsert',
                //     payload: [{
                // }
            }
        });
        expect(syncResp.ok()).toBeTruthy();

        const salesChannelPromise = adminApiContext.get(`./sales-channel/${uuid}`)

        let themeAssignPromise;

        if (currentConfig && currentConfig['storefront.themeSeed']) {
            // check if theme folder exists
            const md5 = data => crypto.createHash('md5')
                .update(data)
                .digest("hex");

            const md5Str = md5(`${storeBaseConfig.defaultThemeId}${uuid}${currentConfig['storefront.themeSeed']}`);

            const themeCssResp = await adminApiContext.head(`${storeBaseConfig.appUrl}theme/${md5Str}/css/all.css`);

            // if theme all.css exists reuse the seed/theme
            if (themeCssResp.status() === 200) {
                themeAssignPromise = adminApiContext.post(`./_action/system-config?salesChannelId=${uuid}`, {
                    data: {
                        'storefront.themeSeed': currentConfig['storefront.themeSeed']
                    }
                });
            }
        }

        if (!themeAssignPromise) {
            themeAssignPromise = adminApiContext.post(`./_action/theme/${storeBaseConfig.defaultThemeId}/assign/${uuid}`);
        }

        const customerData = {
            id: customerUuid,
            email: `customer_${id}@example.com`,
            password: 'shopware',

            defaultShippingAddress: {
                firstName: `${id} admin`,
                lastName: `${id} admin`,
                'city' : 'not',
                'street' : 'not',
                'zipcode' : 'not',
                'countryId' : storeBaseConfig.deCountryId,
            },
            firstName: `${id} admin`,
            lastName: `${id} admin`,

            salesChannelId: uuid,
            groupId: customerGroupUuid,
            customerNumber: `${customerUuid}`,
            defaultPaymentMethodId: storeBaseConfig.invoicePaymentMethodId,
        };

        const customerRespPromise = adminApiContext.post('./customer?_response', {
            data: customerData
        });

        const [customerResp, themeAssignResp, salesChannelResp] = await Promise.all([customerRespPromise, themeAssignPromise, salesChannelPromise]);

        expect(customerResp.ok()).toBeTruthy();
        expect(themeAssignResp.ok()).toBeTruthy();
        expect(salesChannelResp.ok()).toBeTruthy();

        const customer = await customerResp.json();
        const salesChannel = await salesChannelResp.json();

        await use({
            salesChannel: salesChannel.data,
            customer: {... customer.data, password: customerData.password },
            url: baseUrl,
        });
    }, { scope: 'worker' }],

    storefrontPage: async ({ defaultStorefront, browser, storeBaseConfig }, use) => {
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

    anonStorefrontPage: async ({ defaultStorefront, browser, storeBaseConfig }, use) => {
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
                        // @ts-expect-error
                        currencyId: storeBaseConfig.eurCurrencyId,
                        // @ts-expect-error
                        gross: 10,
                        // @ts-expect-error
                        linked: false,
                        // @ts-expect-error
                        net: 8.4,
                    },
                ],
            }
        });

        expect(newProduct.ok()).toBeTruthy();

        // Allow access to new product in test
        const newProductValue = await newProduct.json();
        await use(newProductValue.data)

        // Delete product after the test is done
        await adminApiContext.delete(`./product/${productUuid}`);
    },

    salesChannelProduct: async ({ adminApiContext, defaultStorefront, product }, use) => {
        const syncResp = await adminApiContext.post('./_action/sync', {
            data: {
                'add product to sales channel': {
                    entity: 'product_visibility',
                    action: 'upsert',
                    payload: [{
                        productId: product.id,
                        salesChannelId: defaultStorefront.salesChannel.id,
                        visibility: 30,
                    }]
                },
                'add product to root navigation': {
                    entity: 'product_category',
                    action: 'upsert',
                    payload: [{
                        productId: product.id,
                        categoryId: defaultStorefront.salesChannel.navigationCategoryId,
                    }]
                },
            }
        });

        expect(syncResp.ok()).toBeTruthy();

        await use(product)
    },
});
