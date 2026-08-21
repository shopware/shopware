import { test as base } from '@playwright/test';
import { FixtureTypes, expandAdminMenu } from '@shopware-ag/acceptance-test-suite';

/**
 * Ensures every Visual test starts with an expanded admin sidebar. Scoped to the `Visual`
 * project only - see the PR description for why this is needed.
 *
 * Overrides `AdminPage`, not `page`: `ShopAdmin` and every `Admin*` page object depend on
 * `AdminPage` directly (see `Actors.ts` / `AdministrationPages.ts`), and the base suite's own
 * `page` fixture is just `async ({ AdminPage }, use) => use(AdminPage)` - so `page` alone is
 * never actually requested by these tests, and overriding it here would silently do nothing.
 */
export const test = base.extend<FixtureTypes>({
    AdminPage: async ({ AdminPage }, use, testInfo) => {
        if (testInfo.project.name === 'Visual') {
            await expandAdminMenu(AdminPage);
        }

        await use(AdminPage);
    },
});
