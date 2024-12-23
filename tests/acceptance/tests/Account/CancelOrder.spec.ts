import { test } from '@fixtures/AcceptanceTest';

// Annotate entire file as serial run.
test.describe.configure({ mode: 'serial' });

test('Customers are able to cancel orders in storefront account.', { tag: '@Order @Account' }, async ({
    ShopCustomer,
    StorefrontAccountOrder,
    TestDataService,
    Login,
}) => {

    const product = await TestDataService.createBasicProduct();
    const customer = await TestDataService.createCustomer();
    const order = await TestDataService.createOrder(
        [{ product: product, quantity: 5 }],
        customer
    );

    await TestDataService.setSystemConfig({ 'core.cart.enableOrderRefunds': true });

    await ShopCustomer.attemptsTo(Login(customer));
    await ShopCustomer.goesTo(StorefrontAccountOrder.url());
    const orderItemLocators = await StorefrontAccountOrder.getOrderByOrderNumber(order.orderNumber);
    await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText('Open');
    await orderItemLocators.orderActionsButton.click();
    await orderItemLocators.orderCancelButton.click();
    await StorefrontAccountOrder.dialogOrderCancelButton.click();
    await ShopCustomer.goesTo(StorefrontAccountOrder.url());
    await ShopCustomer.expects(orderItemLocators.orderShippingStatus).toContainText('Open');
    await ShopCustomer.expects(orderItemLocators.orderPaymentStatus).toContainText('Open');
    await ShopCustomer.expects(orderItemLocators.orderPaymentMethod).toContainText('Invoice');
    await ShopCustomer.expects(orderItemLocators.orderShippingMethod).toContainText('Standard');
    await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText('Cancelled');
    await ShopCustomer.expects(orderItemLocators.orderStatus).not.toContainText('Open');
});

test('Customers are able to cancel orders on the final checkout page in storefront account.', { tag: '@Order @Account' }, async ({
    ShopCustomer,
    StorefrontAccountOrder,
    TestDataService,
    Login,
    StorefrontCheckoutOrderEdit,
}) => {

    const product = await TestDataService.createBasicProduct();
    const customer = await TestDataService.createCustomer();
    const order = await TestDataService.createOrder(
        [{ product: product, quantity: 5 }],
        customer
    );

    await TestDataService.setSystemConfig({ 'core.cart.enableOrderRefunds': true });

    await ShopCustomer.attemptsTo(Login(customer));
    await ShopCustomer.goesTo(StorefrontAccountOrder.url());
    const orderItemLocators = await StorefrontAccountOrder.getOrderByOrderNumber(order.orderNumber);
    await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText('Open');
    await orderItemLocators.orderActionsButton.click();
    await orderItemLocators.orderChangePaymentMethodButton.click();
    await StorefrontCheckoutOrderEdit.orderCancelButton.click();
    await StorefrontCheckoutOrderEdit.dialogOrderCancelButton.click();
    await ShopCustomer.goesTo(StorefrontAccountOrder.url());
    await ShopCustomer.expects(orderItemLocators.orderShippingStatus).toContainText('Open');
    await ShopCustomer.expects(orderItemLocators.orderPaymentStatus).toContainText('Open');
    await ShopCustomer.expects(orderItemLocators.orderPaymentMethod).toContainText('Invoice');
    await ShopCustomer.expects(orderItemLocators.orderShippingMethod).toContainText('Standard');
    await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText('Cancelled');
    await ShopCustomer.expects(orderItemLocators.orderStatus).not.toContainText('Open');
});

test('Customers are not able to cancel orders on the final checkout page in storefront account.', { tag: '@Order @Account' }, async ({
    ShopCustomer,
    StorefrontAccountOrder,
    TestDataService,
    Login,
    StorefrontCheckoutOrderEdit,
}) => {

    const product = await TestDataService.createBasicProduct();
    const customer = await TestDataService.createCustomer();
    const order = await TestDataService.createOrder(
        [{ product: product, quantity: 5 }],
        customer
    );

    await TestDataService.setSystemConfig({ 'core.cart.enableOrderRefunds': false });

    await ShopCustomer.attemptsTo(Login(customer));
    await ShopCustomer.goesTo(StorefrontAccountOrder.url());
    const orderItemLocators = await StorefrontAccountOrder.getOrderByOrderNumber(order.orderNumber);
    await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText('Open');
    await orderItemLocators.orderActionsButton.click();
    await orderItemLocators.orderChangePaymentMethodButton.click();
    await ShopCustomer.expects(StorefrontCheckoutOrderEdit.orderCancelButton).not.toBeVisible();
});

test('Customers are not able to cancel orders in storefront account.', { tag: '@Order @Account' }, async ({
    ShopCustomer,
    StorefrontAccountOrder,
    TestDataService,
    Login,
}) => {

    const product = await TestDataService.createBasicProduct();
    const customer = await TestDataService.createCustomer();
    const order = await TestDataService.createOrder(
        [{ product: product, quantity: 5 }],
        customer
    );

    await TestDataService.setSystemConfig({ 'core.cart.enableOrderRefunds': false });

    await ShopCustomer.attemptsTo(Login(customer));
    await ShopCustomer.goesTo(StorefrontAccountOrder.url());
    const orderItemLocators = await StorefrontAccountOrder.getOrderByOrderNumber(order.orderNumber);
    await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText('Open');
    await orderItemLocators.orderActionsButton.click();
    await ShopCustomer.expects(orderItemLocators.orderCancelButton).not.toBeVisible();
});
