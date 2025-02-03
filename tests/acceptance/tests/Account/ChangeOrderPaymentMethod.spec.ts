import { test } from '@fixtures/AcceptanceTest';

test('Customers can update the payment method for an existing order in the storefront account.', { tag: '@Order @Account' }, async ({
    ShopCustomer,
    StorefrontAccountOrder,
    StorefrontCheckoutOrderEdit,
    TestDataService,
    Login,
}) => {
    const product = await TestDataService.createBasicProduct();
    const customer = await TestDataService.createCustomer();
    const order = await TestDataService.createOrder(
        [{ product: product, quantity: 5 }],
        customer
    );

    const newPaymentMethod = await TestDataService.createBasicPaymentMethod({ afterOrderEnabled: true });
    await TestDataService.assignSalesChannelPaymentMethod(TestDataService.defaultSalesChannel.id, newPaymentMethod.id);

    await ShopCustomer.attemptsTo(Login(customer));
    await ShopCustomer.goesTo(StorefrontAccountOrder.url());
    const orderItemLocators = await StorefrontAccountOrder.getOrderByOrderNumber(order.orderNumber);
    await ShopCustomer.expects(orderItemLocators.orderPaymentMethod).toContainText('Invoice');
    
    await orderItemLocators.orderActionsButton.click();
    await orderItemLocators.orderChangePaymentMethodButton.click();

    await StorefrontCheckoutOrderEdit.getPaymentMethodButton(newPaymentMethod.name).click();
    await StorefrontCheckoutOrderEdit.completePaymentButton.click();

    await ShopCustomer.goesTo(StorefrontAccountOrder.url());
    await ShopCustomer.expects(orderItemLocators.orderPaymentMethod).toContainText(newPaymentMethod.name);
});