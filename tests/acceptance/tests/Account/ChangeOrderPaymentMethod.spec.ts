import { test } from '@fixtures/AcceptanceTest';

test('Customers can update the payment method for an existing order in the storefront account.', { tag: ['@Order', '@Account', '@Storefront'] }, async ({
    ShopCustomer,
    StorefrontAccountOrder,
    StorefrontCheckoutOrderEdit,
    TestDataService,
    Login,
    SelectPaymentMethod,
}) => {
    const product = await TestDataService.createBasicProduct();
    const customer = await TestDataService.createCustomer();
    const order = await TestDataService.createOrder(
        [{ product: product, quantity: 5 }],
        customer
    );

    const untouchedOrder = await TestDataService.createOrder(
        [{ product: product, quantity: 1 }],
        customer
    )

    const newPaymentMethod = await TestDataService.createBasicPaymentMethod({ afterOrderEnabled: true });
    await TestDataService.assignSalesChannelPaymentMethod(TestDataService.defaultSalesChannel.id, newPaymentMethod.id);

    await ShopCustomer.attemptsTo(Login(customer));
    await ShopCustomer.goesTo(StorefrontAccountOrder.url());

    const untouchedOrderItemLocators = await StorefrontAccountOrder.getOrderByOrderNumber(untouchedOrder.orderNumber);
    await ShopCustomer.expects(untouchedOrderItemLocators.orderPaymentMethod).toContainText('Invoice');

    const orderItemLocators = await StorefrontAccountOrder.getOrderByOrderNumber(order.orderNumber);
    await ShopCustomer.expects(orderItemLocators.orderPaymentMethod).toContainText('Invoice');

    await ShopCustomer.presses(orderItemLocators.orderActionsButton);
    await ShopCustomer.presses(orderItemLocators.orderChangePaymentMethodButton);

    await ShopCustomer.attemptsTo(SelectPaymentMethod(newPaymentMethod.name));
    await ShopCustomer.presses(StorefrontCheckoutOrderEdit.completePaymentButton);

    await ShopCustomer.goesTo(StorefrontAccountOrder.url());
    await ShopCustomer.expects(orderItemLocators.orderPaymentMethod).toContainText(newPaymentMethod.name);
    // check that other order is not touched
    await ShopCustomer.expects(untouchedOrderItemLocators.orderPaymentMethod).toContainText('Invoice');
});