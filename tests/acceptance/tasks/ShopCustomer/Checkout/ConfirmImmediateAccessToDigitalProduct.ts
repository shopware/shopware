import { test as base } from '@playwright/test';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import type { Task } from '@fixtures/Task';

export const ConfirmImmediateAccessToDigitalProduct = base.extend<{ ConfirmImmediateAccessToDigitalProduct: Task }, FixtureTypes>({
    ConfirmImmediateAccessToDigitalProduct: async ({ shopCustomer, checkoutConfirmPage }, use)=> {
        const task = () => {
            return async function ConfirmImmediateAccessToDigitalProduct() {
                await checkoutConfirmPage.immediateAccessToDigitalProductCheckbox.check();
                await shopCustomer.expects(checkoutConfirmPage.immediateAccessToDigitalProductCheckbox).toBeChecked();
            }
        };

        await use(task);
    },
});
