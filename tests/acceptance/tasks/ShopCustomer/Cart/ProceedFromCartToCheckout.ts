import { test as base } from '@playwright/test';

export const ProceedFromCartToCheckout = base.extend({
    ProceedFromCartToCheckout: async ({ shopCustomer, checkoutCartPage, checkoutConfirmPage }, use) => {
        const task = () => {
            return async function ProceedFromCartToCheckout() {
                await checkoutCartPage.goToCheckoutButton.click();
                await shopCustomer.expects(checkoutConfirmPage.headline).toBeVisible();
            }
        }

        use(task);
    },
});
