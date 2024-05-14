import { test as base } from '@playwright/test';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import type { Task } from '@fixtures/Task';

export const ProceedFromProductToCheckout = base.extend<{ ProceedFromProductToCheckout: Task }, FixtureTypes>({
    ProceedFromProductToCheckout: async ({ shopCustomer, productDetailPage, checkoutConfirmPage }, use)=> {
        const task = () => {
            return async function ProceedFromProductToCheckout() {
                await productDetailPage.offCanvasCartGoToCheckoutButton.click();

                await shopCustomer.expects(checkoutConfirmPage.headline).toBeVisible();
            }
        };

        await use(task);
    },
});
