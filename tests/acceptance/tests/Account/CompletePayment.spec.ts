import { test, expect, getOrderTransactionId } from '@fixtures/AcceptanceTest';

test('Complete payment on an order after the payment failed.', { tag: ['@Order', '@Account', '@Storefront'] }, async ({
    ShopCustomer,
    StorefrontAccountOrder,
    StorefrontAccountOrderEdit,
    TestDataService,
    AdminApiContext,
    Login,
 
}) => {
    const product = await TestDataService.createBasicProduct();
    const customer = await TestDataService.createCustomer();
    const productQuantity = 5;
    const order = await TestDataService.createOrder(
        [{ product: product, quantity: productQuantity }],
        customer
    );
    const orderTransactionId = await getOrderTransactionId(order.id, AdminApiContext);
    const orderTransactionUpdateResponse = await AdminApiContext.post(`./_action/order_transaction/${orderTransactionId}/state/cancel`, {});
    expect(orderTransactionUpdateResponse.ok()).toBeTruthy();
    await ShopCustomer.attemptsTo(Login(customer));
    await ShopCustomer.goesTo(StorefrontAccountOrder.url());
    const orderItemLocators = await StorefrontAccountOrder.getOrderByOrderNumber(order.orderNumber);
    await ShopCustomer.expects(orderItemLocators.orderPaymentStatus).toHaveText('Cancelled');
    await ShopCustomer.expects(orderItemLocators.orderStatus).toHaveText('Complete payment');

    await ShopCustomer.presses(orderItemLocators.orderStatusLink);
    await ShopCustomer.expects(StorefrontAccountOrderEdit.headline).toBeVisible();
    await ShopCustomer.selectsRadioButton(StorefrontAccountOrderEdit.paymentMethodRadioGroup, 'Invoice');
    await ShopCustomer.presses(StorefrontAccountOrderEdit.completePaymentButton);
    await ShopCustomer.expects(StorefrontAccountOrderEdit.editCompletedHeadline).toBeVisible();

    await ShopCustomer.goesTo(StorefrontAccountOrder.url());
    const orderItemLocatorsNew = await StorefrontAccountOrder.getOrderByOrderNumber(order.orderNumber);
    await ShopCustomer.expects(orderItemLocatorsNew.orderPaymentStatus).toHaveText('Open');
});
