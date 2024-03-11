import { test as base } from '@playwright/test';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import type { Task } from '@fixtures/Task';

export const SelectCashOnDeliveryPaymentOption = base.extend<{ SelectCashOnDeliveryPaymentOption: Task }, FixtureTypes>({
    SelectCashOnDeliveryPaymentOption: async ({ shopCustomer, checkoutConfirmPage }, use)=> {
        const task = () => {
            return async function SelectCashOnDeliveryPaymentOption() {
                await checkoutConfirmPage.paymentCashOnDelivery.check();
                await shopCustomer.expects(checkoutConfirmPage.paymentCashOnDelivery).toBeChecked();
            }
        };

        await use(task);
    },
});
