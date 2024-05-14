import { test as base } from '@playwright/test';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import type { Task } from '@fixtures/Task';

export const SelectStandardShippingOption = base.extend<{ SelectStandardShippingOption: Task }, FixtureTypes>({
    SelectStandardShippingOption: async ({ shopCustomer, checkoutConfirmPage }, use)=> {
        const task = () => {
            return async function SelectStandardShippingOption() {
                await checkoutConfirmPage.shippingStandard.check();
                await shopCustomer.expects(checkoutConfirmPage.shippingStandard).toBeChecked();
            }
        };

        await use(task);
    },
});
