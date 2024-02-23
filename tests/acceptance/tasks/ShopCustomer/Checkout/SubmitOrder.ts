import { test as base } from '@playwright/test';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import type { Task } from '@fixtures/Task';

export const SubmitOrder = base.extend<{ SubmitOrder: Task }, FixtureTypes>({
    SubmitOrder: async ({ shopCustomer, checkoutConfirmPage, checkoutFinishPage }, use)=> {
        const task = () => {
            return async function SubmitOrder() {
                await checkoutConfirmPage.submitOrderButton.click();
                await shopCustomer.expects(checkoutFinishPage.headline).toBeVisible();
            }
        };

        await use(task);
    },
});
