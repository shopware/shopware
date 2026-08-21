import { test as base } from '@playwright/test';
import { FixtureTypes, expandAdminMenu } from '@shopware-ag/acceptance-test-suite';

export const test = base.extend<FixtureTypes>({
    AdminPage: async ({ AdminPage }, use, testInfo) => {
        if (testInfo.project.name === 'Visual') {
            await expandAdminMenu(AdminPage);
        }

        await use(AdminPage);
    },
});
