import { test as base } from '@playwright/test';

export const SubmitOrder = base.extend({
    SubmitOrder: async ({ shopCustomer, checkoutConfirmPage, checkoutFinishPage }, use)=> {
        const task = () => {
            return async function SubmitOrder() {
                await checkoutConfirmPage.submitOrderButton.click();
                await shopCustomer.expects(checkoutFinishPage.headline).toBeVisible();
            }
        };

        use(task);
    },
});
