import { mergeTests } from '@playwright/test';
import { test as workerFixtures } from './WorkerFixtures';
import { test as setupFixtures } from './SetupFixtures';
import { test as dataFixtures } from './../test-data/DataFixtures';
import { test as storefrontPagesFixtures } from '@page-objects/StorefrontPages';
import { test as administrationPagesFixtures } from '@page-objects/AdministrationPages';
import { test as shopCustomerTasks } from '@tasks/ShopCustomerTasks';
import { test as shopAdminTasks } from '@tasks/ShopAdminTasks';

export * from '@playwright/test';

export const test = mergeTests(
    workerFixtures,
    setupFixtures,
    dataFixtures,
    storefrontPagesFixtures,
    administrationPagesFixtures,
    shopCustomerTasks,
    shopAdminTasks,
);
