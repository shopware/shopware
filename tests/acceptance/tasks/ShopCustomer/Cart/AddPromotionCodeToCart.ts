import { test as base } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

export const AddPromotionCodeToCart = base.extend<{ AddPromotionCodeToCart: Task }, FixtureTypes>({
    AddPromotionCodeToCart: async ({ ShopCustomer, StorefrontCheckoutCart }, use)=> {
        const task = (promotionName, promotionCode) => {
            return async function AddPromotionCodeToCart() {
                await ShopCustomer.expects(StorefrontCheckoutCart.headline).toBeVisible();

                await StorefrontCheckoutCart.enterPromoInput.fill(promotionCode);
                await StorefrontCheckoutCart.enterPromoInput.press('Enter');

                await ShopCustomer.expects(StorefrontCheckoutCart.page.getByText(promotionName)).toBeVisible();
            }
        };

        await use(task);
    },
});
