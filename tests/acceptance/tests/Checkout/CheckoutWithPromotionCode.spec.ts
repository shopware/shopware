import { test, expect } from '@fixtures/AcceptanceTest';

test('Registered shop customer should be able to use promotion code during checkout.', { tag: '@Checkout' }, async ({
    ShopCustomer,
    AdminApiContext,
    TestDataService,
    DefaultSalesChannel,
    StorefrontCheckoutCart,
    StorefrontCheckoutConfirm,
    StorefrontCheckoutFinish,
    // eslint-disable-next-line @typescript-eslint/no-unused-vars, no-unused-vars
    CartWithProductData,
    Login,
    AddPromotionCodeToCart,
    ProceedFromCartToCheckout,
    ConfirmTermsAndConditions,
    SubmitOrder,
}) => {
    const promotion = await TestDataService.createPromotionWithCode();

    await ShopCustomer.attemptsTo(Login());

    await ShopCustomer.goesTo(StorefrontCheckoutCart.url());

    // Value of test product with price of €10 and quantity of 10.
    await ShopCustomer.expects(StorefrontCheckoutCart.grandTotalPrice).toHaveText('€100.00*');

    await ShopCustomer.attemptsTo(AddPromotionCodeToCart(promotion.name, promotion.code));
    await ShopCustomer.attemptsTo(ProceedFromCartToCheckout());
    await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());

    // Value of test product with price of €10 and quantity of 10 and 10% discount.
    await ShopCustomer.expects(StorefrontCheckoutConfirm.grandTotalPrice).toHaveText('€90.00*');

    await ShopCustomer.attemptsTo(SubmitOrder());
    await ShopCustomer.expects(StorefrontCheckoutFinish.page.getByText(promotion.name)).toBeVisible();
    await ShopCustomer.expects(StorefrontCheckoutFinish.grandTotalPrice).toHaveText('€90.00*');

    const orderId = StorefrontCheckoutFinish.getOrderId();

    TestDataService.addCreatedRecord('order', orderId);

    await test.step('Validate that the order was submitted successfully.', async () => {
        const orderResponse = await AdminApiContext.get(`order/${orderId}`);

        expect(orderResponse.ok()).toBeTruthy();

        const order = await orderResponse.json();

        expect(order.data).toEqual(
            expect.objectContaining({
                price: expect.objectContaining({
                    totalPrice: 90,
                }),
                orderCustomer: expect.objectContaining({
                    email: DefaultSalesChannel.customer.email,
                }),
            })
        );
    });
});
