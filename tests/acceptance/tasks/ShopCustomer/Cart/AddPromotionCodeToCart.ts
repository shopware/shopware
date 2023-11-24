import { test as base } from '@playwright/test';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import type { Task } from '@fixtures/Task';

export const AddPromotionCodeToCart = base.extend<{ AddPromotionCodeToCart: Task }, FixtureTypes>({
    AddPromotionCodeToCart: async ({ shopCustomer, checkoutCartPage }, use)=> {
        const task = (promotionName, promotionCode) => {
            return async function AddPromotionCodeToCart() {
                await shopCustomer.expects(checkoutCartPage.headline).toBeVisible();

                await checkoutCartPage.enterDiscountInput.fill(promotionCode);
                await checkoutCartPage.enterDiscountInput.press('Enter');

                await shopCustomer.expects(checkoutCartPage.page.getByText(promotionName)).toBeVisible();
            }
        };

        await use(task);
    },
});
