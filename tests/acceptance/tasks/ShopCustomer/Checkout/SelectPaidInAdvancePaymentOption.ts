import { test as base } from '@playwright/test';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import type { Task } from '@fixtures/Task';

export const SelectPaidInAdvancePaymentOption = base.extend<{ SelectPaidInAdvancePaymentOption: Task }, FixtureTypes>({
    SelectPaidInAdvancePaymentOption: async ({ shopCustomer, checkoutConfirmPage }, use)=> {
        const task = () => {
            return async function SelectPaidInAdvancePaymentOption() {
                await checkoutConfirmPage.paymentPaidInAdvance.check();
                await shopCustomer.expects(checkoutConfirmPage.paymentPaidInAdvance).toBeChecked();
            }
        };

        await use(task);
    },
});
