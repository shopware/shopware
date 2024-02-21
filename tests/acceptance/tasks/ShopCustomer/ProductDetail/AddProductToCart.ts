import { test as base } from '@playwright/test';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import type { Task } from '@fixtures/Task';

export const AddProductToCart = base.extend<{ AddProductToCart: Task }, FixtureTypes>({
    AddProductToCart: async ({ shopCustomer, productDetailPage }, use)=> {
        const task = (productData, quantity = '1') => {
            return async function AddProductToCart() {
                await productDetailPage.quantitySelect.fill(quantity);

                await productDetailPage.addToCartButton.click();

                await shopCustomer.expects(productDetailPage.offCanvasCartTitle).toBeVisible();
                await shopCustomer.expects(productDetailPage.offCanvasCart.getByText(productData.name)).toBeVisible();
            }
        };

        await use(task);
    },
});
