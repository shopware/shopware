import { test as base } from '@playwright/test';
import { FixtureTypes, expandAdminMenu } from '@shopware-ag/acceptance-test-suite';

/**
 * Ensures every Visual test starts with an expanded admin sidebar. Scoped to the `Visual`
 * project only - see the PR description for why this is needed.
 */
export const test = base.extend<FixtureTypes>({
    page: async ({ page }, use, testInfo) => {
        if (testInfo.project.name === 'Visual') {
            await expandAdminMenu(page);
        }

        await use(page);
    },
});
