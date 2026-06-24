import { test } from '@fixtures/AcceptanceTest';

const AGENTIC_COMMERCE_TYPE_ID = '5e29f9890c4d4d519a1c7f9d5c24b7c1';

test(
    'Agentic Commerce sales channel detail page shows deprecation banner when SwagAgenticCommerce is not installed, and clicking the install button causes no JS error',
    { tag: ['@SalesChannel', '@AgenticCommerce'] },
    async ({ ShopAdmin, TestDataService, page }) => {
        // Create an Agentic Commerce sales channel
        const sc = TestDataService.defaultSalesChannel as unknown as {
            paymentMethodId: string;
            shippingMethodId: string;
        };
        const response = await TestDataService.AdminApiClient.post('sales-channel?_response=detail', {
            data: {
                name: `Agentic Commerce Test ${Date.now()}`,
                typeId: AGENTIC_COMMERCE_TYPE_ID,
                languageId: TestDataService.defaultLanguageId,
                currencyId: TestDataService.defaultCurrencyId,
                navigationCategoryId: TestDataService.defaultCategoryId,
                customerGroupId: TestDataService.defaultCustomerGroupId,
                paymentMethodId: sc.paymentMethodId,
                shippingMethodId: sc.shippingMethodId,
                countryId: TestDataService.defaultCountryId,
                accessKey: `ACTEST${Math.random().toString(36).substring(2, 11).toUpperCase()}`,
                currencies: [{ id: TestDataService.defaultCurrencyId }],
                languages: [{ id: TestDataService.defaultLanguageId }],
                countries: [{ id: TestDataService.defaultCountryId }],
                paymentMethods: [{ id: sc.paymentMethodId }],
                shippingMethods: [{ id: sc.shippingMethodId }],
            },
        });
        if (!response.ok()) {
            const body = await response.text();
            throw new Error(`Failed to create Agentic Commerce sales channel (${response.status()}): ${body}`);
        }
        const responseData = await response.json();
        const salesChannelId = (responseData.data as { id: string }).id;

        TestDataService.addCreatedRecord('sales-channel', salesChannelId);

        // Capture any JS errors before navigating
        const jsErrors: string[] = [];
        page.on('pageerror', (err: Error) => jsErrors.push(err.message));

        await ShopAdmin.goesTo(`/admin#/sw/sales/channel/detail/${salesChannelId}/base`);

        const pluginInstalled = await page.evaluate(() => {
            const shopware = (globalThis as any).Shopware;
            return !!(shopware?.Context?.app?.config?.bundles?.SwagAgenticCommerce);
        });

        if (!pluginInstalled) {
            await test.step('deprecation banner is visible', async () => {
                await ShopAdmin.expects(page.locator('.mt-banner')).toBeVisible();
            });

            await test.step('clicking the install button always navigates somewhere', async () => {
                const extensionStoreDetailExists = await page.evaluate(() => {
                    const shopware = (globalThis as any).Shopware;
                    return !!(shopware?.Context?.app?.config?.bundles?.SwagExtensionStore);
                });

                await page.locator('.mt-banner .mt-button').click();
                await page.waitForTimeout(500);

                if (jsErrors.length > 0) {
                    throw new Error(`Unexpected JS errors after button click:\n${jsErrors.join('\n')}`);
                }

                if (extensionStoreDetailExists) {
                    // SwagExtensionStore installed: should navigate to the plugin's detail page
                    await ShopAdmin.expects(page).toHaveURL(/sw\/extension\/store\/detail\/21761/);
                } else {
                    // SwagExtensionStore not installed: should navigate to the landing page to install it first
                    await ShopAdmin.expects(page).toHaveURL(/sw\/extension\/store/);
                }
            });
        } else {
            await test.step('banner is hidden when plugin is installed', async () => {
                await ShopAdmin.expects(page.locator('.mt-banner')).not.toBeVisible();
            });
        }
    },
);
