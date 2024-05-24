import { test as base } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

export const ConfirmImmediateAccessToDigitalProduct = base.extend<{ ConfirmImmediateAccessToDigitalProduct: Task }, FixtureTypes>({
    ConfirmImmediateAccessToDigitalProduct: async ({ ShopCustomer, StorefrontCheckoutConfirm }, use)=> {
        const task = () => {
            return async function ConfirmImmediateAccessToDigitalProduct() {
                await StorefrontCheckoutConfirm.immediateAccessToDigitalProductCheckbox.check();
                await ShopCustomer.expects(StorefrontCheckoutConfirm.immediateAccessToDigitalProductCheckbox).toBeChecked();
            }
        };

        await use(task);
    },
});
