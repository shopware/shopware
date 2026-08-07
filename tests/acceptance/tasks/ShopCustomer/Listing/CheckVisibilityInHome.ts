import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';
import { test as base } from '@playwright/test';

export const CheckVisibilityInHome = base.extend<{ CheckVisibilityInHome: Task }, FixtureTypes>({
    CheckVisibilityInHome: async ({ ShopCustomer, StorefrontHome, StoreApiContext, TestDataService, InstanceMeta }, use) => {
        const task = (productName: string) => {
            return async () => {
                /**
                 * Wait for the product to be indexed via a cheap Store API request instead of
                 * clearing all caches and re-rendering the whole homepage on every retry.
                 */
                await ShopCustomer.expects(async () => {
                    const response = await StoreApiContext.post('product', {
                        data: {
                            filter: [
                                {
                                    type: 'equals',
                                    field: 'name',
                                    value: productName,
                                },
                            ],
                            limit: 1,
                        },
                    });
                    ShopCustomer.expects(response.ok()).toBeTruthy();

                    const { elements } = await response.json();
                    ShopCustomer.expects(elements.length).toBeGreaterThan(0);
                }).toPass({
                    intervals: [
                        500,
                        1_000,
                    ], // retry after 0.5 seconds, then every second
                });

                const productLocators = await StorefrontHome.getListingItemByProductName(productName);

                // The homepage listing can lag behind the Store API, so render it with a
                // cache busting parameter until the product shows up. On hosted instances
                // (SaaS and PaaS) the listing is OpenSearch-backed and only reflects new
                // products after an index refresh, triggered by the delayed cache clear.
                await ShopCustomer.expects(async () => {
                    if (InstanceMeta.isSaaS || InstanceMeta.isPaaS) {
                        await TestDataService.clearCaches();
                    }
                    await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
                    await ShopCustomer.expects(productLocators.productName).toBeVisible();
                }).toPass({
                    intervals: [
                        1_000,
                        2_500,
                    ], // retry after 1 second, then every 2.5 seconds
                });
            };
        };

        await use(task);
    },
});
