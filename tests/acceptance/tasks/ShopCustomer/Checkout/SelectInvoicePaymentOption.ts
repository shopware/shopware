import { test as base } from '@playwright/test';

export const SelectInvoicePaymentOption = base.extend({
    SelectInvoicePaymentOption: async ({ shopCustomer, checkoutConfirmPage }, use)=> {
        const task = () => {
            return async function SelectInvoicePaymentOption() {
                await checkoutConfirmPage.paymentInvoice.check();
                await shopCustomer.expects(checkoutConfirmPage.paymentInvoice).toBeChecked();
            }
        };

        use(task);
    },
});
