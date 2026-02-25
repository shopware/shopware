import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';
import { test as base } from '@playwright/test';

export const CheckVisibilityInHome = base.extend<{ CheckVisibilityInHome: Task }, FixtureTypes>({
    CheckVisibilityInHome: async ({ ShopCustomer, StorefrontHome }, use) => {
        const task = (productName: string) => {
            return async () => {

                const productLocators = await StorefrontHome.getListingItemByProductName(productName);

                await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
                await ShopCustomer.expects(productLocators.productName).toBeVisible();
            }
        };

        await use(task);
    },
});
