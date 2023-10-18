import { test as base } from '@playwright/test';

export const SelectStandardShippingOption = base.extend({
    SelectStandardShippingOption: async ({ shopCustomer, checkoutConfirmPage }, use)=> {
        const task = () => {
            return async function SelectStandardShippingOption() {
                await checkoutConfirmPage.shippingStandard.check();
                await shopCustomer.expects(checkoutConfirmPage.shippingStandard).toBeChecked();
            }
        };

        use(task);
    },
});
