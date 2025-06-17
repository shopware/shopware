import { test as base } from '@playwright/test';
import { Product, FixtureTypes } from '@shopware-ag/acceptance-test-suite';

export interface HomeProducts {
    HomeProduct: Product;
    HomeProducts: Product[];
}

export const test = base.extend<FixtureTypes & HomeProducts>({
    HomeProduct: async ({ TestDataService, CheckVisibilityInHome }, use) => {
        const product = await TestDataService.createBasicProduct();

        await CheckVisibilityInHome(product.name)();

        await use(product);
    },

    HomeProducts: async ({ TestDataService, CheckVisibilityInHome }, use) => {
        const product1 = await TestDataService.createBasicProduct();
        const product2 = await TestDataService.createBasicProduct();
        const product3 = await TestDataService.createBasicProduct();

        await CheckVisibilityInHome(product1.name)();
        await CheckVisibilityInHome(product2.name)();
        await CheckVisibilityInHome(product3.name)();

        await use([product1, product2, product3]);
    },
});