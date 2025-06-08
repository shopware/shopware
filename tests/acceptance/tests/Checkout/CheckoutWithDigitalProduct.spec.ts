import { test, expect, getOrderTransactionId } from '@fixtures/AcceptanceTest';

test('Registered shop customer should be able to buy a digital product.', { tag: ['@Checkout', '@DigitalProduct'] }, async ({
    ShopCustomer,
    AdminApiContext,
    TestDataService,
    StorefrontProductDetail,
    StorefrontCheckoutFinish,
    StorefrontAccountOrder,
    Login,
    AddProductToCart,
    ProceedFromProductToCheckout,
    ConfirmTermsAndConditions,
    ConfirmImmediateAccessToDigitalProduct,
    SelectInvoicePaymentOption,
    SubmitOrder,
    DownloadDigitalProductFromOrderAndExpectContentToBe,
}) => {
    const fileContent = 'This is a test.';
    const digitalProduct = await TestDataService.createDigitalProduct(fileContent);

    await ShopCustomer.attemptsTo(Login());

    await ShopCustomer.goesTo(StorefrontProductDetail.url(digitalProduct));

    await ShopCustomer.attemptsTo(AddProductToCart(digitalProduct));
    await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());

    await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
    await ShopCustomer.attemptsTo(ConfirmImmediateAccessToDigitalProduct());
    await ShopCustomer.attemptsTo(SelectInvoicePaymentOption());

    await ShopCustomer.attemptsTo(SubmitOrder());

    const orderId = StorefrontCheckoutFinish.getOrderId();

    TestDataService.addCreatedRecord('order', orderId);

    await test.step('Set the order to "paid", so the customer can access the file.', async () => {
        const orderTransactionId = await getOrderTransactionId(orderId, AdminApiContext);
        const orderTransactionUpdateResponse = await AdminApiContext.post(`./_action/order_transaction/${orderTransactionId}/state/paid`, {});
        expect(orderTransactionUpdateResponse.ok()).toBeTruthy();
    });

    await test.step('Verify that customer can access the digital product.', async () => {
        await ShopCustomer.goesTo(StorefrontAccountOrder.url());

        // Download the digital product and check if the content is equal to what was uploaded.
        await ShopCustomer.attemptsTo(DownloadDigitalProductFromOrderAndExpectContentToBe(fileContent));
    });
});
