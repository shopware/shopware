import { test as base } from '@playwright/test';

export const ProceedFromProductToCheckout = base.extend({
    ProceedFromProductToCheckout: async ({ shopCustomer, productDetailPage, checkoutConfirmPage }, use)=> {
        const task = () => {
            return async function ProceedFromProductToCheckout() {
                await productDetailPage.offCanvasCartGoToCheckoutButton.click();

                await shopCustomer.expects(checkoutConfirmPage.headline).toBeVisible();
            }
        };

        use(task);
    },
});
