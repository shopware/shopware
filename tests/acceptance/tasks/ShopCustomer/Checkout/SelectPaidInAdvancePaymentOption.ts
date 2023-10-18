import { test as base } from '@playwright/test';

export const SelectPaidInAdvancePaymentOption = base.extend({
    SelectPaidInAdvancePaymentOption: async ({ shopCustomer, checkoutConfirmPage }, use)=> {
        const task = () => {
            return async function SelectPaidInAdvancePaymentOption() {
                await checkoutConfirmPage.paymentPaidInAdvance.check();
                await shopCustomer.expects(checkoutConfirmPage.paymentPaidInAdvance).toBeChecked();
            }
        };

        use(task);
    },
});
