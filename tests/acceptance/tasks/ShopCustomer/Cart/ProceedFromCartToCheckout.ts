import { test as base } from '@playwright/test';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import type { Task } from '@fixtures/Task';

export const ProceedFromCartToCheckout = base.extend<{ ProceedFromCartToCheckout: Task }, FixtureTypes>({
    ProceedFromCartToCheckout: async ({ shopCustomer, checkoutCartPage, checkoutConfirmPage }, use) => {
        const task = () => {
            return async function ProceedFromCartToCheckout() {
                await checkoutCartPage.goToCheckoutButton.click();
                await shopCustomer.expects(checkoutConfirmPage.headline).toBeVisible();
            }
        }

        await use(task);
    },
});
