import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';
import { test as base } from '@playwright/test';

export const CheckVisibilityInHome = base.extend<{ CheckVisibilityInHome: Task }, FixtureTypes>({
    CheckVisibilityInHome: async ({ ShopCustomer, StorefrontHome, TestDataService }, use) => {
        const task = (productName: string) => {
            return async () => {

                await TestDataService.clearCaches();
                const productLocators = await StorefrontHome.getListingItemByProductName(productName);

                await ShopCustomer.expects(async () => {
                    await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
                    await ShopCustomer.expects(productLocators.productName).toBeVisible();
                }).toPass({
                    intervals: [1_000, 2_500], // retry after 1 seconds, then every 2.5 seconds
                });
            }
        };

        await use(task);
    },
});
