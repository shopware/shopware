import { mergeTests, test as ShopwareTestSuite } from '@shopware-ag/acceptance-test-suite';
import { test as shopAdminTasks } from '@tasks/ShopAdminTasks';
import { test as shopCustomerTasks } from '@tasks/ShopCustomerTasks';
import { test as HomeProducts } from './HomeProducts';
import { test as CurrentsCoverage } from './CurrentsCoverage';

export * from '@shopware-ag/acceptance-test-suite';

export const test = mergeTests(
  ShopwareTestSuite,
  shopCustomerTasks,
  shopAdminTasks,
  HomeProducts,
  CurrentsCoverage, // may or may not carry fixtures depending on merge implementation & name collisions
);
