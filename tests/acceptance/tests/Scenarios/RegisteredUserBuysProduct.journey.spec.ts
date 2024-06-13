import { test, expect } from '@fixtures/AcceptanceTest';

test('Journey: Registered shop customer buys a product. @journey @checkout', async ({
    ShopCustomer,
    DefaultSalesChannel,
    ProductData,
    AdminApiContext,
    StorefrontProductDetail,
    StorefrontCheckoutConfirm,
    StorefrontCheckoutFinish,
    Login,
    AddProductToCart,
    ProceedFromProductToCheckout,
    ConfirmTermsAndConditions,
    SelectInvoicePaymentOption,
    SelectStandardShippingOption,
    SubmitOrder,
}) => {

    await ShopCustomer.attemptsTo(Login());

    await ShopCustomer.goesTo(StorefrontProductDetail.url(ProductData));
    await ShopCustomer.expects(StorefrontProductDetail.page).toHaveTitle(
        `${ProductData.translated.name} | ${ProductData.productNumber}`
    );

    await ShopCustomer.attemptsTo(AddProductToCart(ProductData));
    await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());

    await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
    await ShopCustomer.attemptsTo(SelectInvoicePaymentOption());
    await ShopCustomer.attemptsTo(SelectStandardShippingOption());

    await ShopCustomer.expects(StorefrontCheckoutConfirm.grandTotalPrice).toHaveText('€10.00*');

    await ShopCustomer.attemptsTo(SubmitOrder());

    await test.step('Validate that the order was submitted successfully.', async () => {
        const orderId = StorefrontCheckoutFinish.getOrderId();
        const orderResponse = await AdminApiContext.get(`order/${orderId}`);

        expect(orderResponse.ok()).toBeTruthy();

        const order = await orderResponse.json();

        expect(order.data).toEqual(
            expect.objectContaining({
                price: expect.objectContaining({
                    totalPrice: 10,
                }),
                orderCustomer: expect.objectContaining({
                    email: DefaultSalesChannel.customer.email,
                }),
            })
        );
    });
});
