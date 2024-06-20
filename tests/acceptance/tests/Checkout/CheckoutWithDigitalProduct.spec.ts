import { test, expect, getOrderTransactionId } from '@fixtures/AcceptanceTest';

test('Registered shop customer should be able to buy a digital product. @checkout', async ({
    ShopCustomer,
    AdminApiContext,
    DigitalProductData,
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
    await ShopCustomer.attemptsTo(Login());

    await ShopCustomer.goesTo(StorefrontProductDetail.url(DigitalProductData.product));

    await ShopCustomer.attemptsTo(AddProductToCart(DigitalProductData.product));
    await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());

    await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
    await ShopCustomer.attemptsTo(ConfirmImmediateAccessToDigitalProduct());
    await ShopCustomer.attemptsTo(SelectInvoicePaymentOption());

    await ShopCustomer.attemptsTo(SubmitOrder());

    const orderId = StorefrontCheckoutFinish.getOrderId();

    await test.step('Set the order to "paid", so the customer can access the file.', async () => {
        const orderTransactionId = await getOrderTransactionId(orderId, AdminApiContext);
        const orderTransactionUpdateResponse = await AdminApiContext.post(`./_action/order_transaction/${orderTransactionId}/state/paid`, {});
        expect(orderTransactionUpdateResponse.ok()).toBeTruthy();
    });

    await test.step('Verify that customer can access the digital product.', async () => {
        await ShopCustomer.goesTo(StorefrontAccountOrder.url());

        // Download the digital product and check if the content is equal to what was uploaded.
        await ShopCustomer.attemptsTo(DownloadDigitalProductFromOrderAndExpectContentToBe(DigitalProductData.fileContent))
    });
});
