import { test, expect } from '@fixtures/AcceptanceTest';

test('Registered shop customer buys a product.', { tag: '@Checkout' }, async ({
    ShopCustomer,
    TestDataService,
    DefaultSalesChannel,
    AdminApiContext,
    StorefrontProductDetail,
    StorefrontCheckoutConfirm,
    StorefrontCheckoutFinish,
    Login_a11y,
    AddProductToCart_a11y,
    ProceedFromProductToCheckout_a11y,
    ConfirmTermsAndConditions_a11y,
    SelectInvoicePaymentOption_a11y,
    SelectStandardShippingOption_a11y,
    SubmitOrder_a11y,
}) => {
    const product = await TestDataService.createBasicProduct();

    await ShopCustomer.attemptsTo(Login_a11y());

    await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
    await ShopCustomer.expects(StorefrontProductDetail.page).toHaveTitle(
        `${product.translated.name} | ${product.productNumber}`
    );

    await ShopCustomer.attemptsTo(AddProductToCart_a11y(product));
    await ShopCustomer.attemptsTo(ProceedFromProductToCheckout_a11y());

    await ShopCustomer.attemptsTo(ConfirmTermsAndConditions_a11y());
    await ShopCustomer.attemptsTo(SelectInvoicePaymentOption_a11y());
    await ShopCustomer.attemptsTo(SelectStandardShippingOption_a11y());

    await ShopCustomer.expects(StorefrontCheckoutConfirm.grandTotalPrice).toContainText('€10.00');

    await ShopCustomer.attemptsTo(SubmitOrder_a11y());
    await ShopCustomer.expects(StorefrontCheckoutFinish.grandTotalPrice).toContainText('€10.00');
    //await ShopCustomer.expects(StorefrontCheckoutFinish.grandTotalPrice).toHaveColorATS('green');

    const orderId = StorefrontCheckoutFinish.getOrderId();

    TestDataService.addCreatedRecord('order', orderId);

    await test.step('Validate that the order was submitted successfully.', async () => {
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
