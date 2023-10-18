import { test as base } from '@playwright/test';

export const SelectExpressShippingOption = base.extend({
    SelectExpressShippingOption: async ({ shopCustomer, checkoutConfirmPage }, use)=> {
        const task = () => {
            return async function SelectExpressShippingOption() {
                await checkoutConfirmPage.shippingExpress.check();
                await shopCustomer.expects(checkoutConfirmPage.shippingExpress).toBeChecked();
            }
        };

        use(task);
    },
});
