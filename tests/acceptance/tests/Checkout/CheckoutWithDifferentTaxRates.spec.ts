import { test, formatPrice } from '@fixtures/AcceptanceTest';

interface TaxRate {
    id: string;
}

let taxRate0: TaxRate | undefined;
let taxRate7: TaxRate | undefined;
let taxRate19: TaxRate | undefined;

test.beforeAll(async ({ AdminApiContext, IdProvider }) => {
    const sharedTaxRates = [
        0,
        7,
        19,
    ].map((taxRate) => {
        const { uuid } = IdProvider.getIdPair();

        return {
            id: uuid,
            name: `Checkout tax ${taxRate}% ${uuid}`,
            taxRate,
        };
    });

    const response = await AdminApiContext.post('_action/sync', {
        data: {
            'create-shared-checkout-taxes': {
                entity: 'tax',
                action: 'upsert',
                payload: sharedTaxRates,
            },
        },
    });

    if (!response.ok()) {
        throw new Error('Could not create the shared checkout tax rates.');
    }

    [
        taxRate0,
        taxRate7,
        taxRate19,
    ] = sharedTaxRates;
});

test.afterAll(async ({ AdminApiContext }) => {
    const taxRates = [
        taxRate0,
        taxRate7,
        taxRate19,
    ].filter((taxRate): taxRate is TaxRate => taxRate !== undefined);

    if (taxRates.length === 0) {
        return;
    }

    const response = await AdminApiContext.post('_action/sync', {
        data: {
            'delete-shared-checkout-taxes': {
                entity: 'tax',
                action: 'delete',
                payload: taxRates,
            },
        },
    });

    if (!response.ok()) {
        throw new Error('Could not delete the shared checkout tax rates.');
    }
});

test(
    'As a customer, I want to perform a checkout process with 19% tax rate with 2 same products.',
    {
        tag: [
            '@Checkout',
            '@Storefront',
        ],
    },
    async ({
        ShopCustomer,
        TestDataService,
        StorefrontProductDetail,
        StorefrontCheckoutConfirm,
        StorefrontCheckoutFinish,
        SelectShippingMethod,
        Login,
        AddProductToCart,
        ProceedFromProductToCheckout,
        ConfirmTermsAndConditions,
        SelectPaymentMethod,
        SubmitOrder,
        StorefrontAccountOrder,
    }) => {
        const productWithTaxRate19 = await TestDataService.createBasicProduct({}, taxRate19!.id);
        let orderNumber: string;

        await test.step('Add 2 identical products to chart, proceed to checkout and validate on confirm page the tax price.', async () => {
            await ShopCustomer.attemptsTo(Login());
            await ShopCustomer.goesTo(StorefrontProductDetail.url(productWithTaxRate19));
            await ShopCustomer.attemptsTo(AddProductToCart(productWithTaxRate19, '2'));
            await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
            await ShopCustomer.expects(StorefrontCheckoutConfirm.taxPrice).toContainText(formatPrice(3.19));
        });

        await test.step('Submit the order, navigate to checkout finish page and validate the tax price.', async () => {
            await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
            await ShopCustomer.attemptsTo(SelectPaymentMethod('Invoice'));
            await ShopCustomer.attemptsTo(SelectShippingMethod('Standard'));

            await ShopCustomer.attemptsTo(SubmitOrder());
            const orderId = StorefrontCheckoutFinish.getOrderId();
            TestDataService.addCreatedRecord('order', orderId);
            orderNumber = await StorefrontCheckoutFinish.getOrderNumber();

            await ShopCustomer.expects(StorefrontCheckoutFinish.taxPrice).toContainText(formatPrice(3.19));
        });

        await test.step('Navigate to customers account order page and validate the orders tax price.', async () => {
            await ShopCustomer.goesTo(StorefrontAccountOrder.url());
            const orderLocators = await StorefrontAccountOrder.getOrderByOrderNumber(orderNumber);
            await ShopCustomer.presses(orderLocators.orderDetailButton);
            await ShopCustomer.expects(orderLocators.taxPrice).toContainText(formatPrice(3.19));
        });
    },
);

test(
    'As a customer, I want to perform a checkout process with 7% tax rate with 2 different products.',
    {
        tag: [
            '@Checkout',
            '@Storefront',
        ],
    },
    async ({
        ShopCustomer,
        TestDataService,
        StorefrontProductDetail,
        StorefrontCheckoutConfirm,
        StorefrontCheckoutFinish,
        SelectShippingMethod,
        Login,
        AddProductToCart,
        ProceedFromProductToCheckout,
        ConfirmTermsAndConditions,
        SelectPaymentMethod,
        SubmitOrder,
        StorefrontAccountOrder,
    }) => {
        const product1WithTaxRate7 = await TestDataService.createBasicProduct({}, taxRate7!.id);
        const product2WithTaxRate7 = await TestDataService.createBasicProduct({}, taxRate7!.id);
        let orderNumber: string;

        await test.step('Add 2 different products with same tax rate to chart, proceed to checkout and validate on confirm page the tax price.', async () => {
            await ShopCustomer.attemptsTo(Login());
            await ShopCustomer.goesTo(StorefrontProductDetail.url(product1WithTaxRate7));
            await ShopCustomer.attemptsTo(AddProductToCart(product1WithTaxRate7));
            await ShopCustomer.goesTo(StorefrontProductDetail.url(product2WithTaxRate7));
            await ShopCustomer.attemptsTo(AddProductToCart(product2WithTaxRate7));
            await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
            await ShopCustomer.expects(StorefrontCheckoutConfirm.taxPrice).toContainText(formatPrice(1.3));
        });

        await test.step('Submit the order, navigate to checkout finish page and validate the tax price.', async () => {
            await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
            await ShopCustomer.attemptsTo(SelectPaymentMethod('Invoice'));
            await ShopCustomer.attemptsTo(SelectShippingMethod('Standard'));

            await ShopCustomer.attemptsTo(SubmitOrder());
            const orderId = StorefrontCheckoutFinish.getOrderId();
            TestDataService.addCreatedRecord('order', orderId);
            orderNumber = await StorefrontCheckoutFinish.getOrderNumber();

            await ShopCustomer.expects(StorefrontCheckoutFinish.taxPrice).toContainText(formatPrice(1.3));
        });

        await test.step('Navigate to customers account order page and validate the orders tax price.', async () => {
            await ShopCustomer.goesTo(StorefrontAccountOrder.url());
            const orderLocators = await StorefrontAccountOrder.getOrderByOrderNumber(orderNumber);
            await ShopCustomer.presses(orderLocators.orderDetailButton);
            await ShopCustomer.expects(orderLocators.taxPrice).toContainText(formatPrice(1.3));
        });
    },
);

test(
    'As a customer, I want to perform a checkout process with 7% and 19% tax rate per product.',
    {
        tag: [
            '@Checkout',
            '@Storefront',
        ],
    },
    async ({
        ShopCustomer,
        TestDataService,
        StorefrontProductDetail,
        StorefrontCheckoutConfirm,
        StorefrontCheckoutFinish,
        SelectShippingMethod,
        Login,
        AddProductToCart,
        ProceedFromProductToCheckout,
        ConfirmTermsAndConditions,
        SelectPaymentMethod,
        SubmitOrder,
        StorefrontAccountOrder,
    }) => {
        const productWithTaxRate19 = await TestDataService.createBasicProduct({}, taxRate19!.id);
        const product1WithTaxRate7 = await TestDataService.createBasicProduct({}, taxRate7!.id);
        let orderNumber: string;

        await test.step('Add 2 different products with different tax rates to chart, proceed to checkout and validate on confirm page the tax price.', async () => {
            await ShopCustomer.attemptsTo(Login());
            await ShopCustomer.goesTo(StorefrontProductDetail.url(productWithTaxRate19));
            await ShopCustomer.attemptsTo(AddProductToCart(productWithTaxRate19, '2'));
            await ShopCustomer.goesTo(StorefrontProductDetail.url(product1WithTaxRate7));
            await ShopCustomer.attemptsTo(AddProductToCart(product1WithTaxRate7, '2'));
            await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
            await ShopCustomer.expects(StorefrontCheckoutConfirm.taxPrice.nth(0)).toContainText(formatPrice(3.19));
            await ShopCustomer.expects(StorefrontCheckoutConfirm.taxPrice.nth(1)).toContainText(formatPrice(1.31));
        });

        await test.step('Submit the order, navigate to checkout finish page and validate the tax price.', async () => {
            await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
            await ShopCustomer.attemptsTo(SelectPaymentMethod('Invoice'));
            await ShopCustomer.attemptsTo(SelectShippingMethod('Standard'));

            await ShopCustomer.attemptsTo(SubmitOrder());
            const orderId = StorefrontCheckoutFinish.getOrderId();
            TestDataService.addCreatedRecord('order', orderId);
            orderNumber = await StorefrontCheckoutFinish.getOrderNumber();

            await ShopCustomer.expects(StorefrontCheckoutFinish.taxPrice.nth(0)).toContainText(formatPrice(3.19));
            await ShopCustomer.expects(StorefrontCheckoutFinish.taxPrice.nth(1)).toContainText(formatPrice(1.31));
        });

        await test.step('Navigate to customers account order page and validate the orders tax price.', async () => {
            await ShopCustomer.goesTo(StorefrontAccountOrder.url());
            const orderLocators = await StorefrontAccountOrder.getOrderByOrderNumber(orderNumber);
            await ShopCustomer.presses(orderLocators.orderDetailButton);
            await ShopCustomer.expects(orderLocators.taxPrice.nth(0)).toContainText(formatPrice(3.19));
            await ShopCustomer.expects(orderLocators.taxPrice.nth(1)).toContainText(formatPrice(1.31));
        });
    },
);

test(
    'As a customer, I want to perform a checkout process with 0% tax rate.',
    {
        tag: [
            '@Checkout',
            '@Storefront',
        ],
    },
    async ({
        ShopCustomer,
        TestDataService,
        StorefrontProductDetail,
        StorefrontCheckoutConfirm,
        StorefrontCheckoutFinish,
        SelectShippingMethod,
        Login,
        AddProductToCart,
        ProceedFromProductToCheckout,
        ConfirmTermsAndConditions,
        SelectPaymentMethod,
        SubmitOrder,
        StorefrontAccountOrder,
    }) => {
        const productWithTaxRate0 = await TestDataService.createBasicProduct({}, taxRate0!.id);
        let orderNumber: string;

        await test.step('Add one product with tax rate to chart, proceed to checkout and validate on confirm page the tax price.', async () => {
            await ShopCustomer.attemptsTo(Login());
            await ShopCustomer.goesTo(StorefrontProductDetail.url(productWithTaxRate0));
            await ShopCustomer.attemptsTo(AddProductToCart(productWithTaxRate0));
            await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
            await ShopCustomer.expects(StorefrontCheckoutConfirm.taxPrice).toContainText(formatPrice(0.0));
        });

        await test.step('Submit the order, navigate to checkout finish page and validate the tax price.', async () => {
            await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
            await ShopCustomer.attemptsTo(SelectPaymentMethod('Invoice'));
            await ShopCustomer.attemptsTo(SelectShippingMethod('Standard'));

            await ShopCustomer.attemptsTo(SubmitOrder());
            const orderId = StorefrontCheckoutFinish.getOrderId();
            TestDataService.addCreatedRecord('order', orderId);
            orderNumber = await StorefrontCheckoutFinish.getOrderNumber();

            await ShopCustomer.expects(StorefrontCheckoutFinish.taxPrice).toContainText(formatPrice(0.0));
        });

        await test.step('Navigate to customers account order page and validate the orders tax price.', async () => {
            await ShopCustomer.goesTo(StorefrontAccountOrder.url());
            const orderLocators = await StorefrontAccountOrder.getOrderByOrderNumber(orderNumber);
            await ShopCustomer.presses(orderLocators.orderDetailButton);
            await ShopCustomer.expects(orderLocators.taxPrice).toContainText(formatPrice(0.0));
        });
    },
);
