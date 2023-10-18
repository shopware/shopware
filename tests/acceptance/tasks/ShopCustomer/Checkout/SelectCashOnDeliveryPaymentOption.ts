import { test as base } from '@playwright/test';

export const SelectCashOnDeliveryPaymentOption = base.extend({
    SelectCashOnDeliveryPaymentOption: async ({ shopCustomer, checkoutConfirmPage }, use)=> {
        const task = () => {
            return async function SelectCashOnDeliveryPaymentOption() {
                await checkoutConfirmPage.paymentCashOnDelivery.check();
                await shopCustomer.expects(checkoutConfirmPage.paymentCashOnDelivery).toBeChecked();
            }
        };

        use(task);
    },
});
