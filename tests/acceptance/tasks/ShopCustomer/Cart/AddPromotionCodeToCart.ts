import { test as base } from '@playwright/test';

export const AddPromotionCodeToCart = base.extend({
    AddPromotionCodeToCart: async ({ shopCustomer, checkoutCartPage }, use)=> {
        const task = (promotionName, promotionCode) => {
            return async function AddPromotionCodeToCart() {
                await shopCustomer.expects(checkoutCartPage.headline).toBeVisible();

                await checkoutCartPage.enterDiscountInput.fill(promotionCode);
                await checkoutCartPage.enterDiscountInput.press('Enter');

                await shopCustomer.expects(checkoutCartPage.page.getByText(promotionName)).toBeVisible();
            }
        };

        use(task);
    },
});
