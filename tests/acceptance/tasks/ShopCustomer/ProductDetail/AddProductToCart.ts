import { test as base } from '@playwright/test';

export const AddProductToCart = base.extend({
    AddProductToCart: async ({ shopCustomer, productDetailPage, salesChannelProduct }, use)=> {
        const task = () => {
            return async function AddProductToCart() {
                await productDetailPage.addToCartButton.click();

                await shopCustomer.expects(productDetailPage.offCanvasCartTitle).toBeVisible();
                await shopCustomer.expects(productDetailPage.offCanvasCart.getByText(salesChannelProduct.name)).toBeVisible();
            }
        };

        use(task);
    },
});
