import { BUNDLED_RESOURCES, mergeTests, test as ShopwareTestSuite } from '@shopware-ag/acceptance-test-suite';
import { test as shopAdminTasks } from '@tasks/ShopAdminTasks';
import { test as shopCustomerTasks } from '@tasks/ShopCustomerTasks';
import { test as HomeProducts } from './HomeProducts';
import { test as VisualTestSetup } from './VisualTestSetup';

export * from '@shopware-ag/acceptance-test-suite';

const translationResources = JSON.parse(JSON.stringify(BUNDLED_RESOURCES)) as typeof BUNDLED_RESOURCES;
translationResources.en['storefront/checkout'].confirm.submitOrder = 'Buy now';

export const test = mergeTests(ShopwareTestSuite, shopCustomerTasks, shopAdminTasks, HomeProducts, VisualTestSetup).extend({
    // eslint-disable-next-line no-empty-pattern
    CustomTranslationResources: async ({}, use) => {
        await use(translationResources);
    },
});
