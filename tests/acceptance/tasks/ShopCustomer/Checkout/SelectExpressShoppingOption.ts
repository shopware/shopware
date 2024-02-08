import { test as base } from '@playwright/test';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import type { Task } from '@fixtures/Task';

export const SelectExpressShippingOption = base.extend<{ SelectExpressShippingOption: Task }, FixtureTypes>({
    SelectExpressShippingOption: async ({ shopCustomer, checkoutConfirmPage }, use)=> {
        const task = () => {
            return async function SelectExpressShippingOption() {
                await checkoutConfirmPage.shippingExpress.check();
                await shopCustomer.expects(checkoutConfirmPage.shippingExpress).toBeChecked();
            }
        };

        await use(task);
    },
});
