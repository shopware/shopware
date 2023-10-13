import { test, expect } from '@fixtures/AcceptanceTest';
import { CheckoutCartPage, CheckoutConfirmPage, CheckoutFinishPage } from '@page-objects/StorefrontPages';

test('Registered shop customer uses a promotion code during checkout. @checkout', async ({
    adminApiContext,
    storefrontPage,
    storeApiContext,
    defaultStorefront,
    salesChannelProduct,
    idProvider,
}) => {
    const promotionCode = `${idProvider.getIdPair().id}`;
    const promotionName = `Test Promotion ${promotionCode}`;

    const cartPage = new CheckoutCartPage(storefrontPage);
    const checkoutConfirmPage = new CheckoutConfirmPage(storefrontPage);
    const checkoutFinishPage = new CheckoutFinishPage(storefrontPage);

    await test.step('Prepare test data.', async () => {
        // Login customer in store API context.
        await storeApiContext.login(defaultStorefront.customer);

        // Create new cart for the shop customer.
        const cartResponse = await storeApiContext.post(`checkout/cart`, {
            data: {
                name: `default-customer-cart-${promotionCode}`,
            },
        });
        expect(cartResponse.ok()).toBeTruthy();

        // Create new line items in the cart.
        const lineItemResponse = await storeApiContext.post('checkout/cart/line-item', {
            data: {
                items: [
                    {
                        type: 'product',
                        referencedId: salesChannelProduct.id,
                        quantity: 10,
                    },
                ],
            },
        });
        expect(lineItemResponse.ok()).toBeTruthy();

        // Create a new promotion code via admin API context.
        const promotionResponse = await adminApiContext.post('promotion?_response=1', {
            data: {
                name: promotionName,
                active: true,
                maxRedemptionsGlobal: 100,
                maxRedemptionsPerCustomer: 10,
                priority: 1,
                exclusive: false,
                useCodes: true,
                useIndividualCodes: false,
                useSetGroups: false,
                preventCombination: true,
                customerRestriction: false,
                code: promotionCode,
                discounts: [
                    {
                        scope: 'cart',
                        type: 'percentage',
                        value: 10,
                        considerAdvancedRules: false,
                    },
                ],
                salesChannels: [
                    {
                        salesChannelId: defaultStorefront.salesChannel.id,
                        priority: 1,
                    },
                ],
            },
        });
        expect(promotionResponse.ok()).toBeTruthy();
    });

    await test.step('Shop customer navigates to cart page.', async () => {
        await cartPage.goto();
        await expect(cartPage.headline).toBeVisible();

        // Value of test product with price of €10 and quantity of 10.
        await expect(cartPage.grandTotalPrice).toHaveText('€100.00*');
    });

    await test.step('Shop customer enters promotion code.', async () => {
        await cartPage.enterDiscountInput.fill(promotionCode);
        await cartPage.enterDiscountInput.press('Enter');

        await expect(cartPage.page.getByText(promotionName)).toBeVisible();

        // Value of test product with price of €10 and quantity of 10 with 10% discount.
        await expect(cartPage.grandTotalPrice).toHaveText('€90.00*');
    });

    await test.step('Shop customer proceeds to checkout.', async () => {
        await cartPage.goToCheckoutButton.click();

        await expect(checkoutConfirmPage.headline).toBeVisible();
    });

    await test.step('Shop customer confirms terms and conditions.', async () => {
        await checkoutConfirmPage.termsAndConditionsCheckbox.check();
        await expect(checkoutConfirmPage.termsAndConditionsCheckbox).toBeChecked();
    });

    await test.step('Shop customer validates price sum.', async () => {
        // Value of test product with price of €10 and quantity of 10 with 10% discount.
        await expect(checkoutConfirmPage.grandTotalPrice).toHaveText('€90.00*');
    });

    await test.step('Shop customer submits order.', async () => {
        await checkoutConfirmPage.submitOrderButton.click();
        await expect(checkoutFinishPage.headline).toBeVisible();
    });

    await test.step('Shop customer validates order summary.', async () => {
        await expect(checkoutFinishPage.page.getByText(promotionName)).toBeVisible();
        await expect(checkoutFinishPage.grandTotalPrice).toHaveText('€90.00');
    });

    await test.step('Validate that the order was submitted successfully.', async () => {
        const orderId = checkoutFinishPage.getOrderId();
        const orderResponse = await adminApiContext.get(`order/${orderId}`);

        expect(orderResponse.ok()).toBeTruthy();

        const order = await orderResponse.json();

        expect(order.data).toEqual(
            expect.objectContaining({
                price: expect.objectContaining({
                    totalPrice: 90,
                }),
                orderCustomer: expect.objectContaining({
                    email: defaultStorefront.customer.email,
                }),
            })
        );
    });
});
