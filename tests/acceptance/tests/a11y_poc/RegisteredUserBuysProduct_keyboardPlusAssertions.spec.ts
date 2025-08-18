import { test, expect } from '@fixtures/AcceptanceTest';

test('Registered shop customer buys a product.', { tag: '@Checkout' }, async ({
    ShopCustomer,
    TestDataService,
    DefaultSalesChannel,
    AdminApiContext,
    StorefrontProductDetail,
    StorefrontCheckoutConfirm,
    StorefrontCheckoutFinish,
    Login_a11yAssert,
    AddProductToCart_a11yAssert,
    ProceedFromProductToCheckout_a11yAssert,
    ConfirmTermsAndConditions_a11yAssert,
    SelectInvoicePaymentOption_a11yAssert,
    SelectStandardShippingOption_a11yAssert,
    SubmitOrder_a11yAssert,
}) => {
    const product = await TestDataService.createBasicProduct();
    const paidInAdvance = await TestDataService.getPaymentMethod('Paid in advance');
    const cashOnDelivery = await TestDataService.getPaymentMethod('Cash on delivery');
    await TestDataService.assignSalesChannelPaymentMethod(DefaultSalesChannel.salesChannel.id, paidInAdvance.id );
    await TestDataService.assignSalesChannelPaymentMethod(DefaultSalesChannel.salesChannel.id, cashOnDelivery.id );

    await ShopCustomer.attemptsTo(Login_a11yAssert());

    await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
    await ShopCustomer.expects(StorefrontProductDetail.page).toHaveTitle(
        `${product.translated.name} | ${product.productNumber}`
    );

    await ShopCustomer.attemptsTo(AddProductToCart_a11yAssert(product));
    await ShopCustomer.attemptsTo(ProceedFromProductToCheckout_a11yAssert());
    await ShopCustomer.attemptsTo(ConfirmTermsAndConditions_a11yAssert());

    //testing radio selection from last to first
    await StorefrontCheckoutConfirm.paymentPaidInAdvance.check();
    await ShopCustomer.attemptsTo(SelectInvoicePaymentOption_a11yAssert());

    //desired radio button is selected by default
    await ShopCustomer.attemptsTo(SelectStandardShippingOption_a11yAssert());
    await ShopCustomer.expects(StorefrontCheckoutConfirm.grandTotalPrice).toContainText('€10.00');

    await ShopCustomer.attemptsTo(SubmitOrder_a11yAssert());
    await ShopCustomer.expects(StorefrontCheckoutFinish.grandTotalPrice).toContainText('€10.00');
    
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
