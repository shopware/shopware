import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { test } from '@fixtures/AcceptanceTest';

const PRODUCT_COMPARISON_TYPE_ID = 'ed535e5722134ac1aa6524f73e26881b';
const TEMPLATE_DIRECTORY = fileURLToPath(
    new URL(
        '../../../../src/Administration/Resources/app/administration/src/module/sw-sales-channel/product-export-templates/google-product-search-de',
        import.meta.url,
    ),
);

test(
    'Google Shopping product comparison preview shows the price of the minimum purchasable quantity for products with graduated prices.',
    {
        tag: [
            '@SalesChannel',
            '@ProductComparison',
            '@Prices',
        ],
    },
    async ({ ShopAdmin, TestDataService, page }) => {
        const product = await TestDataService.createProductWithPriceRange();
        const sc = TestDataService.defaultSalesChannel as unknown as {
            id: string;
            currencyId: string;
            paymentMethodId: string;
            shippingMethodId: string;
        };

        const productExportId = TestDataService.IdProvider.getIdPair().uuid;
        TestDataService.addCreatedRecord('product-export', productExportId);

        const currencyResponse = await TestDataService.AdminApiClient.get(`currency/${sc.currencyId}`);
        const currencyIsoCode = ((await currencyResponse.json()) as { data: { isoCode: string } }).data.isoCode;

        const domainResponse = await TestDataService.AdminApiClient.post('search/sales-channel-domain', {
            data: { limit: 1, filter: [{ type: 'equals', field: 'salesChannelId', value: sc.id }] },
        });
        const domainId = ((await domainResponse.json()) as { data: { id: string }[] }).data[0].id;

        const streamResponse = await TestDataService.AdminApiClient.post('product-stream?_response=detail', {
            data: {
                name: `Google feed stream ${Date.now()}`,
                filters: [
                    {
                        type: 'multi',
                        operator: 'OR',
                        queries: [
                            {
                                type: 'multi',
                                operator: 'AND',
                                queries: [{ type: 'equalsAny', field: 'id', value: product.id }],
                            },
                        ],
                    },
                ],
            },
        });
        const productStreamId = ((await streamResponse.json()) as { data: { id: string } }).data.id;
        TestDataService.addCreatedRecord('product-stream', productStreamId);

        const salesChannelId = TestDataService.IdProvider.getIdPair().uuid;
        const salesChannelResponse = await TestDataService.AdminApiClient.post('sales-channel?_response=detail', {
            data: {
                id: salesChannelId,
                name: `Google feed ${Date.now()}`,
                typeId: PRODUCT_COMPARISON_TYPE_ID,
                languageId: TestDataService.defaultLanguageId,
                currencyId: sc.currencyId,
                navigationCategoryId: TestDataService.defaultCategoryId,
                customerGroupId: TestDataService.defaultCustomerGroupId,
                paymentMethodId: sc.paymentMethodId,
                shippingMethodId: sc.shippingMethodId,
                countryId: TestDataService.defaultCountryId,
                accessKey: `ACTEST${Math.random().toString(36).substring(2, 11).toUpperCase()}`,
                currencies: [{ id: sc.currencyId }],
                languages: [{ id: TestDataService.defaultLanguageId }],
                countries: [{ id: TestDataService.defaultCountryId }],
                paymentMethods: [{ id: sc.paymentMethodId }],
                shippingMethods: [{ id: sc.shippingMethodId }],
            },
        });
        if (!salesChannelResponse.ok()) {
            throw new Error(
                `Failed to create sales channel (${salesChannelResponse.status()}): ${await salesChannelResponse.text()}`,
            );
        }
        TestDataService.addCreatedRecord('sales-channel', salesChannelId);

        const exportResponse = await TestDataService.AdminApiClient.post('product-export?_response=detail', {
            data: {
                id: productExportId,
                productStreamId,
                storefrontSalesChannelId: sc.id,
                salesChannelId,
                salesChannelDomainId: domainId,
                currencyId: sc.currencyId,
                fileName: `google-feed-${productExportId}.xml`,
                accessKey: `ACTEST${Math.random().toString(36).substring(2, 11).toUpperCase()}`,
                encoding: 'UTF-8',
                fileFormat: 'xml',
                includeVariants: false,
                generateByCronjob: false,
                interval: 86400,
                headerTemplate: readFileSync(join(TEMPLATE_DIRECTORY, 'header.xml.twig'), 'utf8'),
                bodyTemplate: readFileSync(join(TEMPLATE_DIRECTORY, 'body.xml.twig'), 'utf8'),
                footerTemplate: readFileSync(join(TEMPLATE_DIRECTORY, 'footer.xml.twig'), 'utf8'),
            },
        });
        if (!exportResponse.ok()) {
            throw new Error(`Failed to create product export (${exportResponse.status()}): ${await exportResponse.text()}`);
        }

        await test.step('Open the product comparison tab of the sales channel', async () => {
            await ShopAdmin.goesTo(`/admin#/sw/sales/channel/detail/${salesChannelId}/product-comparison`);
            await ShopAdmin.expects(page.getByRole('button', { name: 'Generate preview' })).toBeVisible();
        });

        await test.step('Generate the preview and check the exported price', async () => {
            const previewResponsePromise = page.waitForResponse(
                (response) =>
                    response.url().includes('/api/_action/product-export/preview') && response.request().method() === 'POST',
            );
            await page.getByRole('button', { name: 'Generate preview' }).click();

            const previewResponse = await previewResponsePromise;
            ShopAdmin.expects(previewResponse.ok(), await previewResponse.text()).toBeTruthy();
            const preview = (await previewResponse.json()) as { content: string; errors: unknown[] };
            ShopAdmin.expects(preview.errors).toEqual([]);
            ShopAdmin.expects(preview.content).toContain(`<g:price>100.00 ${currencyIsoCode}</g:price>`);
            ShopAdmin.expects(preview.content).not.toContain(`<g:price>70.00 ${currencyIsoCode}</g:price>`);

            await ShopAdmin.expects(page.locator('.sw-modal .sw-code-editor')).toBeVisible();
        });
    },
);
