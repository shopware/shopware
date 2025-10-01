import { test } from '@fixtures/AcceptanceTest';

test(
    'Customers are able to cancel orders in storefront account.',
    { tag: '@Order @Account' },
    async ({ ShopCustomer, StorefrontAccountOrder, TestDataService, Login, Translate }) => {
        const product = await TestDataService.createBasicProduct();
        const customer = await TestDataService.createCustomer();
        const order = await TestDataService.createOrder([{ product: product, quantity: 5 }], customer);

        await TestDataService.setSystemConfig({ 'core.cart.enableOrderRefunds': true });

        await ShopCustomer.attemptsTo(Login(customer));
        await ShopCustomer.goesTo(StorefrontAccountOrder.url());
        const orderItemLocators = await StorefrontAccountOrder.getOrderByOrderNumber(order.orderNumber);
        await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText(
            Translate('administration:order:status.open')
        );
        await orderItemLocators.orderActionsButton.click();
        await orderItemLocators.orderCancelButton.click();
        await StorefrontAccountOrder.dialogOrderCancelButton.click();
        await ShopCustomer.goesTo(StorefrontAccountOrder.url());
        await ShopCustomer.expects(orderItemLocators.orderShippingStatus).toContainText(
            Translate('administration:order:status.open')
        );
        await ShopCustomer.expects(orderItemLocators.orderPaymentStatus).toContainText(
            Translate('administration:order:status.open')
        );
        await ShopCustomer.expects(orderItemLocators.orderPaymentMethod).toContainText(
            Translate('storefront:checkout:payment.invoice')
        );
        await ShopCustomer.expects(orderItemLocators.orderShippingMethod).toContainText(
            Translate('storefront:checkout:shipping.standard')
        );
        await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText(
            Translate('administration:order:status.cancelled')
        );
        await ShopCustomer.expects(orderItemLocators.orderStatus).not.toContainText(
            Translate('administration:order:status.open')
        );
    }
);

test(
    'Customers are able to cancel orders on the final checkout page in storefront account.',
    { tag: '@Order @Account' },
    async ({
        ShopCustomer,
        StorefrontAccountOrder,
        TestDataService,
        Login,
        StorefrontCheckoutOrderEdit,
        Translate,
    }) => {
        const product = await TestDataService.createBasicProduct();
        const customer = await TestDataService.createCustomer();
        const order = await TestDataService.createOrder([{ product: product, quantity: 5 }], customer);

        await TestDataService.setSystemConfig({ 'core.cart.enableOrderRefunds': true });

        await ShopCustomer.attemptsTo(Login(customer));
        await ShopCustomer.goesTo(StorefrontAccountOrder.url());
        const orderItemLocators = await StorefrontAccountOrder.getOrderByOrderNumber(order.orderNumber);
        const openStatus = Translate('administration:order:status.open');
        await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText(openStatus);
        await orderItemLocators.orderActionsButton.click();
        await orderItemLocators.orderChangePaymentMethodButton.click();
        await StorefrontCheckoutOrderEdit.orderCancelButton.click();
        await StorefrontCheckoutOrderEdit.dialogOrderCancelButton.click();
        await ShopCustomer.goesTo(StorefrontAccountOrder.url());
        await ShopCustomer.expects(orderItemLocators.orderShippingStatus).toContainText(openStatus);
        await ShopCustomer.expects(orderItemLocators.orderPaymentStatus).toContainText(openStatus);
        await ShopCustomer.expects(orderItemLocators.orderPaymentMethod).toContainText(
            Translate('storefront:checkout:payment.invoice')
        );
        await ShopCustomer.expects(orderItemLocators.orderShippingMethod).toContainText(
            Translate('storefront:checkout:shipping.standard')
        );
        await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText(
            Translate('administration:order:status.cancelled')
        );
        await ShopCustomer.expects(orderItemLocators.orderStatus).not.toContainText(openStatus);
    }
);

test(
    'Customers are not able to cancel orders on the final checkout page in storefront account.',
    { tag: '@Order @Account' },
    async ({
        ShopCustomer,
        StorefrontAccountOrder,
        TestDataService,
        Login,
        StorefrontCheckoutOrderEdit,
        Translate,
    }) => {
        const product = await TestDataService.createBasicProduct();
        const customer = await TestDataService.createCustomer();
        const order = await TestDataService.createOrder([{ product: product, quantity: 5 }], customer);

        await TestDataService.setSystemConfig({ 'core.cart.enableOrderRefunds': false });

        await ShopCustomer.attemptsTo(Login(customer));
        await ShopCustomer.goesTo(StorefrontAccountOrder.url());
        const orderItemLocators = await StorefrontAccountOrder.getOrderByOrderNumber(order.orderNumber);
        await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText(
            Translate('administration:order:status.open')
        );
        await orderItemLocators.orderActionsButton.click();
        await orderItemLocators.orderChangePaymentMethodButton.click();
        await ShopCustomer.expects(StorefrontCheckoutOrderEdit.orderCancelButton).not.toBeVisible();
    }
);

test(
    'Customers are not able to cancel orders in storefront account.',
    { tag: '@Order @Account' },
    async ({ ShopCustomer, StorefrontAccountOrder, TestDataService, Login, Translate }) => {
        const product = await TestDataService.createBasicProduct();
        const customer = await TestDataService.createCustomer();
        const order = await TestDataService.createOrder([{ product: product, quantity: 5 }], customer);

        await TestDataService.setSystemConfig({ 'core.cart.enableOrderRefunds': false });

        await ShopCustomer.attemptsTo(Login(customer));
        await ShopCustomer.goesTo(StorefrontAccountOrder.url());
        const orderItemLocators = await StorefrontAccountOrder.getOrderByOrderNumber(order.orderNumber);
        await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText(
            Translate('administration:order:status.open')
        );
        await orderItemLocators.orderActionsButton.click();
        await ShopCustomer.expects(orderItemLocators.orderCancelButton).not.toBeVisible();
    }
);
