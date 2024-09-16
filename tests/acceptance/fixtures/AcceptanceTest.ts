import { test as ShopwareTestSuite, mergeTests } from '@shopware-ag/acceptance-test-suite';
import { test as shopCustomerTasks } from '@tasks/ShopCustomerTasks';
import { test as shopAdminTasks } from '@tasks/ShopAdminTasks';

export * from '@shopware-ag/acceptance-test-suite';

export const test = mergeTests(
    ShopwareTestSuite,
    shopCustomerTasks,
    shopAdminTasks,
);
