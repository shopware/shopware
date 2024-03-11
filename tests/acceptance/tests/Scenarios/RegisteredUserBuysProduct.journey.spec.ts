import { test, expect } from '@fixtures/AcceptanceTest';

test('Journey: Registered shop customer buys a product. @journey @checkout', async ({
    shopCustomer,
    defaultStorefront,
    productData,
    adminApiContext,
    productDetailPage,
    checkoutConfirmPage,
    checkoutFinishPage,
    Login,
    AddProductToCart,
    ProceedFromProductToCheckout,
    ConfirmTermsAndConditions,
    SelectInvoicePaymentOption,
    SelectStandardShippingOption,
    SubmitOrder,
}) => {
    test.info().annotations.push({
        type: 'Acceptance Criteria',
        description: 'Shop customer should be able to login.',
    }, {
        type: 'Acceptance Criteria',
        description: 'Shop customer should be able to add a product to the cart.',
    }, {
        type: 'Acceptance Criteria',
        description: 'Shop customer should be able to perform a checkout.',
    });

    await shopCustomer.attemptsTo(Login());

    await shopCustomer.goesTo(productDetailPage);
    await shopCustomer.expects(productDetailPage.page).toHaveTitle(
        `${productData.translated.name} | ${productData.productNumber}`
    );

    await shopCustomer.attemptsTo(AddProductToCart(productData));
    await shopCustomer.attemptsTo(ProceedFromProductToCheckout());

    await shopCustomer.attemptsTo(ConfirmTermsAndConditions());
    await shopCustomer.attemptsTo(SelectInvoicePaymentOption());
    await shopCustomer.attemptsTo(SelectStandardShippingOption());

    await shopCustomer.expects(checkoutConfirmPage.grandTotalPrice).toHaveText('€10.00*');

    await shopCustomer.attemptsTo(SubmitOrder());

    await test.step('Validate that the order was submitted successfully.', async () => {
        const orderId = checkoutFinishPage.getOrderId();
        const orderResponse = await adminApiContext.get(`order/${orderId}`);

        await expect(orderResponse.ok()).toBeTruthy();

        const order = await orderResponse.json();

        await expect(order.data).toEqual(
            expect.objectContaining({
                price: expect.objectContaining({
                    totalPrice: 10,
                }),
                orderCustomer: expect.objectContaining({
                    email: defaultStorefront.customer.email,
                }),
            })
        );
    });
});
