import { test as base } from '@playwright/test';
import { Product, FixtureTypes } from '@shopware-ag/acceptance-test-suite';

export interface HomeProducts {
    HomeProduct: Product;
    HomeProducts: Product[];
}

export const test = base.extend<FixtureTypes & HomeProducts>({
    HomeProduct: async ({ TestDataService, VisibleInHome }, use) => {
        const product = await TestDataService.createBasicProduct();

        await VisibleInHome(product.name)();

        await use(product);
    },

    HomeProducts: async ({ TestDataService, VisibleInHome }, use) => {
        const product1 = await TestDataService.createBasicProduct();
        const product2 = await TestDataService.createBasicProduct();
        const product3 = await TestDataService.createBasicProduct();

        await VisibleInHome(product1.name)();
        await VisibleInHome(product2.name)();
        await VisibleInHome(product3.name)();

        await use([product1, product2, product3]);
    },
});