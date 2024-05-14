import { expect, test as base } from '@playwright/test';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import type { Task } from '@fixtures/Task';

export const FRWSalesChannelSelectionPossibility = base.extend<{ FRWSalesChannelSelectionPossibility: Task }, FixtureTypes>({
    FRWSalesChannelSelectionPossibility: async ({ firstRunWizardPage }, use)=> {
        const task = (salesChannelName: string) => {
            return async function FRWSalesChannelSelectionPossibility() {
                await firstRunWizardPage.salesChannelSelectionMultiSelect.click();
                // eslint-disable-next-line playwright/valid-expect
                await expect(firstRunWizardPage.salesChannelSelectionList.filter({ hasText: salesChannelName })).toBeVisible();
            }
        };

        await use(task);
    },
});
