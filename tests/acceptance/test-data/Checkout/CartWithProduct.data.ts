import { test as base } from '@playwright/test';
import { expect } from '@fixtures/AcceptanceTest';
import { FixtureTypes } from '@fixtures/FixtureTypes';

export const CartWithProductData = base.extend<FixtureTypes>({
    cartWithProductData: async ({ storeApiContext, defaultStorefront, productData }, use) => {
        // Login customer in store API context.
        await storeApiContext.login(defaultStorefront.customer);

        // Create new cart for the shop customer.
        const cartResponse = await storeApiContext.post(`checkout/cart`, {
            data: {
                name: `default-customer-cart`,
            },
        });
        await expect(cartResponse.ok()).toBeTruthy();

        // Create new line items in the cart.
        const lineItemResponse = await storeApiContext.post('checkout/cart/line-item', {
            data: {
                items: [
                    {
                        type: 'product',
                        referencedId: productData.id,
                        quantity: 10,
                    },
                ],
            },
        });
        await expect(lineItemResponse.ok()).toBeTruthy();

        const cartData = await lineItemResponse.json();

        await use(cartData);
    },
});
