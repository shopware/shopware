import { test as base, expect } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

export const FRWSalesChannelSelectionPossibility = base.extend<{ FRWSalesChannelSelectionPossibility: Task }, FixtureTypes>({
    FRWSalesChannelSelectionPossibility: async ({ AdminFirstRunWizard }, use) => {
        const task = (salesChannelName: string) => {
            return async function FRWSalesChannelSelectionPossibility() {
                await AdminFirstRunWizard.salesChannelSelectionMultiSelect.click();
                await expect(AdminFirstRunWizard.salesChannelSelectionList.filter({ hasText: salesChannelName }).first()).toBeVisible();
            }
        };

        await use(task);
    },
});
