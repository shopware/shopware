import { test, expect } from '@fixtures/AcceptanceTest';

test('Registered shop customer uses a promotion code during checkout. @checkout @priority1', async ({
    shopCustomer,
    adminApiContext,
    defaultStorefront,
    checkoutCartPage,
    checkoutConfirmPage,
    checkoutFinishPage,
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    cartWithProductData,
    promotionWithCodeData,
    AddPromotionCodeToCart,
    ProceedFromCartToCheckout,
    ConfirmTermsAndConditions,
    SubmitOrder,
}) => {
    const promotionCode = promotionWithCodeData.promotionCode;
    const promotionName = promotionWithCodeData.promotionName;

    await shopCustomer.goesTo(checkoutCartPage);

    // Value of test product with price of €10 and quantity of 10.
    await shopCustomer.expects(checkoutCartPage.grandTotalPrice).toHaveText('€100.00*');

    await shopCustomer.attemptsTo(AddPromotionCodeToCart(promotionName, promotionCode));
    await shopCustomer.attemptsTo(ProceedFromCartToCheckout());
    await shopCustomer.attemptsTo(ConfirmTermsAndConditions());

    // Value of test product with price of €10 and quantity of 10 and 10% discount.
    await shopCustomer.expects(checkoutConfirmPage.grandTotalPrice).toHaveText('€90.00*');

    await shopCustomer.attemptsTo(SubmitOrder());
    await shopCustomer.expects(checkoutFinishPage.page.getByText(promotionName)).toBeVisible();
    await shopCustomer.expects(checkoutFinishPage.grandTotalPrice).toHaveText('€90.00');

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
