import { test } from '@fixtures/AcceptanceTest';

test(
    'Customers are able to cancel orders in storefront account.',
    { tag: ['@Order', '@Account', '@Storefront'] },
    async ({ ShopCustomer, StorefrontAccountOrder, TestDataService, Login }) => {
        const product = await TestDataService.createBasicProduct();
        const customer = await TestDataService.createCustomer();
        const order = await TestDataService.createOrder([{ product: product, quantity: 5 }], customer);

        const untouchedOrder = await TestDataService.createOrder([{ product: product, quantity: 1 }], customer);

        await TestDataService.setSystemConfig({ 'core.cart.enableOrderRefunds': true });

        await ShopCustomer.attemptsTo(Login(customer));
        await ShopCustomer.goesTo(StorefrontAccountOrder.url());

        const untouchedOrderItemLocators = await StorefrontAccountOrder.getOrderByOrderNumber(untouchedOrder.orderNumber);
        await ShopCustomer.expects(untouchedOrderItemLocators.orderStatus).toContainText('Open');

        const orderItemLocators = await StorefrontAccountOrder.getOrderByOrderNumber(order.orderNumber);
        await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText('Open');
        await ShopCustomer.presses(orderItemLocators.orderActionsButton);
        await ShopCustomer.presses(orderItemLocators.orderCancelButton);
        await ShopCustomer.presses(StorefrontAccountOrder.dialogOrderCancelButton);
        await ShopCustomer.goesTo(StorefrontAccountOrder.url());
        await ShopCustomer.expects(orderItemLocators.orderShippingStatus).toContainText('Open');
        await ShopCustomer.expects(orderItemLocators.orderPaymentStatus).toContainText('Open');
        await ShopCustomer.expects(orderItemLocators.orderPaymentMethod).toContainText('Invoice');
        await ShopCustomer.expects(orderItemLocators.orderShippingMethod).toContainText('Standard');
        await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText('Cancelled');
        await ShopCustomer.expects(orderItemLocators.orderStatus).not.toContainText('Open');
        // ensure other order is unaffected
        await ShopCustomer.expects(untouchedOrderItemLocators.orderStatus).toContainText('Open');
    }
);

test(
    'Customers are able to cancel orders on the final checkout page in storefront account.',
    { tag: ['@Order', '@Account', '@Storefront'] },
    async ({ ShopCustomer, StorefrontAccountOrder, TestDataService, Login, StorefrontCheckoutOrderEdit }) => {
        const product = await TestDataService.createBasicProduct();
        const customer = await TestDataService.createCustomer();
        const order = await TestDataService.createOrder([{ product: product, quantity: 5 }], customer);

        await TestDataService.setSystemConfig({ 'core.cart.enableOrderRefunds': true });

        await ShopCustomer.attemptsTo(Login(customer));
        await ShopCustomer.goesTo(StorefrontAccountOrder.url());
        const orderItemLocators = await StorefrontAccountOrder.getOrderByOrderNumber(order.orderNumber);
        await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText('Open');
        await ShopCustomer.presses(orderItemLocators.orderActionsButton);
        await ShopCustomer.presses(orderItemLocators.orderChangePaymentMethodButton);
        await ShopCustomer.presses(StorefrontCheckoutOrderEdit.orderCancelButton);
        await ShopCustomer.presses(StorefrontCheckoutOrderEdit.dialogOrderCancelButton);
        await ShopCustomer.goesTo(StorefrontAccountOrder.url());
        await ShopCustomer.expects(orderItemLocators.orderShippingStatus).toContainText('Open');
        await ShopCustomer.expects(orderItemLocators.orderPaymentStatus).toContainText('Open');
        await ShopCustomer.expects(orderItemLocators.orderPaymentMethod).toContainText('Invoice');
        await ShopCustomer.expects(orderItemLocators.orderShippingMethod).toContainText('Standard');
        await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText('Cancelled');
        await ShopCustomer.expects(orderItemLocators.orderStatus).not.toContainText('Open');
    }
);

test(
    'Customers are not able to cancel orders on the final checkout page in storefront account.',
    { tag: ['@Order', '@Account', '@Storefront'] },
    async ({ ShopCustomer, StorefrontAccountOrder, TestDataService, Login, StorefrontCheckoutOrderEdit }) => {
        const product = await TestDataService.createBasicProduct();
        const customer = await TestDataService.createCustomer();
        const order = await TestDataService.createOrder([{ product: product, quantity: 5 }], customer);

        await TestDataService.setSystemConfig({ 'core.cart.enableOrderRefunds': false });

        await ShopCustomer.attemptsTo(Login(customer));
        await ShopCustomer.goesTo(StorefrontAccountOrder.url());
        const orderItemLocators = await StorefrontAccountOrder.getOrderByOrderNumber(order.orderNumber);
        await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText('Open');
        await ShopCustomer.presses(orderItemLocators.orderActionsButton);
        await ShopCustomer.presses(orderItemLocators.orderChangePaymentMethodButton);
        await ShopCustomer.expects(StorefrontCheckoutOrderEdit.orderCancelButton).not.toBeVisible();
    }
);

test(
    'Customers are not able to cancel orders in storefront account.',
    { tag: ['@Order', '@Account', '@Storefront'] },
    async ({ ShopCustomer, StorefrontAccountOrder, TestDataService, Login }) => {
        const product = await TestDataService.createBasicProduct();
        const customer = await TestDataService.createCustomer();
        const order = await TestDataService.createOrder([{ product: product, quantity: 5 }], customer);

        await TestDataService.setSystemConfig({ 'core.cart.enableOrderRefunds': false });

        await ShopCustomer.attemptsTo(Login(customer));
        await ShopCustomer.goesTo(StorefrontAccountOrder.url());
        const orderItemLocators = await StorefrontAccountOrder.getOrderByOrderNumber(order.orderNumber);
        await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText('Open');
        await ShopCustomer.presses(orderItemLocators.orderActionsButton);
        await ShopCustomer.expects(orderItemLocators.orderCancelButton).not.toBeVisible();
    }
);
