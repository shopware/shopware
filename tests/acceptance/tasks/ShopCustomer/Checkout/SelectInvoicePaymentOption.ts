import { test as base } from '@playwright/test';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import type { Task } from '@fixtures/Task';

export const SelectInvoicePaymentOption = base.extend<{ SelectInvoicePaymentOption: Task }, FixtureTypes>({
    SelectInvoicePaymentOption: async ({ shopCustomer, checkoutConfirmPage }, use)=> {
        const task = () => {
            return async function SelectInvoicePaymentOption() {
                await checkoutConfirmPage.paymentInvoice.check();
                await shopCustomer.expects(checkoutConfirmPage.paymentInvoice).toBeChecked();
            }
        };

        await use(task);
    },
});
