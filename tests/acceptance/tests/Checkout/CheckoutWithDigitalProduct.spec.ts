import { test, expect } from '@fixtures/AcceptanceTest';
import { getOrderTransactionId } from '@fixtures/Helper';  

test('Registered shop customer should be able to buy a digital product. @checkout', async ({
    shopCustomer,
    adminApiContext,
    digitalProductData,
    productDetailPage,
    checkoutFinishPage,
    accountOrderPage,
    Login,
    AddProductToCart,
    ProceedFromProductToCheckout,
    ConfirmTermsAndConditions,
    ConfirmImmediateAccessToDigitalProduct,
    SelectInvoicePaymentOption,
    SubmitOrder,
    DownloadDigitalProductFromOrderAndExpectContentToBe,
}) => {
    test.info().annotations.push({
        type: 'Acceptance Criteria',
        description: 'Shop customer should be able to add a digital product to the cart.',
    }, {
        type: 'Acceptance Criteria',
        description: 'Shop customer should be able to perform a checkout.',
    }, {
        type: 'Acceptance Criteria',
        description: 'Shop customer should be able to access the digital product.',
    }
    );

    await shopCustomer.attemptsTo(Login());

    await shopCustomer.goesTo(productDetailPage);

    await shopCustomer.attemptsTo(AddProductToCart(digitalProductData.product));
    await shopCustomer.attemptsTo(ProceedFromProductToCheckout());

    await shopCustomer.attemptsTo(ConfirmTermsAndConditions());
    await shopCustomer.attemptsTo(ConfirmImmediateAccessToDigitalProduct());
    await shopCustomer.attemptsTo(SelectInvoicePaymentOption());
    
    await shopCustomer.attemptsTo(SubmitOrder());
    
    const orderId = checkoutFinishPage.getOrderId();

    await test.step('Set the order to "paid", so the customer can access the file.', async () => {
        const orderTransactionId = await getOrderTransactionId(orderId, adminApiContext);
        const orderTransactionUpdateResponse = await adminApiContext.post(`./_action/order_transaction/${orderTransactionId}/state/paid`, {});
        await expect(orderTransactionUpdateResponse.ok()).toBeTruthy();
    });

    await test.step('Verify that customer can access the digital product.', async () => {
        await shopCustomer.goesTo(accountOrderPage);

        // Download the digital product and check if the content is equal to what was uploaded.
        await shopCustomer.attemptsTo(DownloadDigitalProductFromOrderAndExpectContentToBe(digitalProductData.fileContent))
    }); 
});