import { test as base } from '@playwright/test';
import { expect } from '@fixtures/AcceptanceTest';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import { components } from '@shopware/api-client/admin-api-types';

export const ProductData = base.extend<FixtureTypes>({
    productData: async ({ idProvider, storeBaseConfig, adminApiContext, defaultStorefront }, use) => {

        // Generate unique IDs
        const { id: productId, uuid: productUuid } = idProvider.getIdPair();
        const productName = `Test_product_${productId}`;

        // Create product
        const productResponse = await adminApiContext.post('./product?_response', {
            data: {
                active: true,
                stock: 10,
                taxId: storeBaseConfig.taxId,
                id: productUuid,
                name: productName,
                productNumber: 'TEST-' + productId,
                price: [
                    {
                        currencyId: storeBaseConfig.eurCurrencyId,
                        gross: 10,
                        linked: false,
                        net: 8.4,
                    },
                ],
            },
        });

        expect(productResponse.ok()).toBeTruthy();

        // Allow access to new product in test
        const { data: product } = (await productResponse.json()) as { data: components['schemas']['Product'] };

        // Assign product to sales channel
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

        // Use product data in the test
        await use(product);

        // Delete product after the test is done
        const cleanupResponse = await adminApiContext.delete(`./product/${productUuid}`);
        expect(cleanupResponse.ok()).toBeTruthy();
    },
});
