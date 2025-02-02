import { test } from '@fixtures/AcceptanceTest';

test(
    'As a customer, I want to perform a checkout process with 19% tax rate with 2 same products.',
    { tag: ['@Checkout'] },
    async ({
        ShopCustomer,
        TestDataService,
        StorefrontProductDetail,
        StorefrontCheckoutConfirm,
        StorefrontCheckoutFinish,
        SelectStandardShippingOption,
        Login,
        AddProductToCart,
        ProceedFromProductToCheckout,
        ConfirmTermsAndConditions,
        SelectInvoicePaymentOption,
        SubmitOrder,
        StorefrontAccountOrder,
    }) => {
        const taxRate19 = await TestDataService.createTaxRate({ taxRate: 19.0 });
        const productWithTaxRate19 = await TestDataService.createBasicProduct({}, taxRate19.id);
        let orderNumber: string;

        await test.step('Add 2 identical products to chart, proceed to checkout and validate on confirm page the tax price.', async () => {
            await ShopCustomer.attemptsTo(Login());
            await ShopCustomer.goesTo(StorefrontProductDetail.url(productWithTaxRate19));
            await ShopCustomer.attemptsTo(AddProductToCart(productWithTaxRate19, '2'));
            await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
            await ShopCustomer.expects(StorefrontCheckoutConfirm.taxPrice).toHaveText('€3.19');
        });

        await test.step('Submit the order, navigate to checkout finish page and validate the tax price.', async () => {
            await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
            await ShopCustomer.attemptsTo(SelectInvoicePaymentOption());
            await ShopCustomer.attemptsTo(SelectStandardShippingOption());

            await ShopCustomer.attemptsTo(SubmitOrder());
            const orderId = StorefrontCheckoutFinish.getOrderId();
            TestDataService.addCreatedRecord('order', orderId);
            orderNumber = await StorefrontCheckoutFinish.getOrderNumber();

            await ShopCustomer.expects(StorefrontCheckoutFinish.taxPrice).toHaveText('€3.19');
        });

        await test.step('Navigate to customers account order page and validate the orders tax price.', async () => {
            await ShopCustomer.goesTo(StorefrontAccountOrder.url());
            const orderLocators = await StorefrontAccountOrder.getOrderByOrderNumber(orderNumber);
            await orderLocators.orderDetailButton.click();
            await ShopCustomer.expects(orderLocators.taxPrice).toHaveText('€3.19');
        });
    }
);

test(
    'As a customer, I want to perform a checkout process with 7% tax rate with 2 different products.',
    { tag: ['@Checkout'] },
    async ({
        ShopCustomer,
        TestDataService,
        StorefrontProductDetail,
        StorefrontCheckoutConfirm,
        StorefrontCheckoutFinish,
        SelectStandardShippingOption,
        Login,
        AddProductToCart,
        ProceedFromProductToCheckout,
        ConfirmTermsAndConditions,
        SelectInvoicePaymentOption,
        SubmitOrder,
        StorefrontAccountOrder,
    }) => {
        const taxRate7 = await TestDataService.createTaxRate({ taxRate: 7.0 });
        const product1WithTaxRate7 = await TestDataService.createBasicProduct({}, taxRate7.id);
        const product2WithTaxRate7 = await TestDataService.createBasicProduct({}, taxRate7.id);
        let orderNumber: string;

        await test.step('Add 2 different products with same tax rate to chart, proceed to checkout and validate on confirm page the tax price.', async () => {
            await ShopCustomer.attemptsTo(Login());
            await ShopCustomer.goesTo(StorefrontProductDetail.url(product1WithTaxRate7));
            await ShopCustomer.attemptsTo(AddProductToCart(product1WithTaxRate7));
            await ShopCustomer.goesTo(StorefrontProductDetail.url(product2WithTaxRate7));
            await ShopCustomer.attemptsTo(AddProductToCart(product2WithTaxRate7));
            await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
            await ShopCustomer.expects(StorefrontCheckoutConfirm.taxPrice).toHaveText('€1.30');
        });

        await test.step('Submit the order, navigate to checkout finish page and validate the tax price.', async () => {
            await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
            await ShopCustomer.attemptsTo(SelectInvoicePaymentOption());
            await ShopCustomer.attemptsTo(SelectStandardShippingOption());

            await ShopCustomer.attemptsTo(SubmitOrder());
            const orderId = StorefrontCheckoutFinish.getOrderId();
            TestDataService.addCreatedRecord('order', orderId);
            orderNumber = await StorefrontCheckoutFinish.getOrderNumber();

            await ShopCustomer.expects(StorefrontCheckoutFinish.taxPrice).toHaveText('€1.30');
        });

        await test.step('Navigate to customers account order page and validate the orders tax price.', async () => {
            await ShopCustomer.goesTo(StorefrontAccountOrder.url());
            const orderLocators = await StorefrontAccountOrder.getOrderByOrderNumber(orderNumber);
            await orderLocators.orderDetailButton.click();
            await ShopCustomer.expects(orderLocators.taxPrice).toHaveText('€1.30');
        });
    }
);

test(
    'As a customer, I want to perform a checkout process with 7% and 19% tax rate per product.',
    { tag: ['@Checkout'] },
    async ({
        ShopCustomer,
        TestDataService,
        StorefrontProductDetail,
        StorefrontCheckoutConfirm,
        StorefrontCheckoutFinish,
        SelectStandardShippingOption,
        Login,
        AddProductToCart,
        ProceedFromProductToCheckout,
        ConfirmTermsAndConditions,
        SelectInvoicePaymentOption,
        SubmitOrder,
        StorefrontAccountOrder,
    }) => {
        const taxRate19 = await TestDataService.createTaxRate({ taxRate: 19.0 });
        const taxRate7 = await TestDataService.createTaxRate({ taxRate: 7.0 });
        const productWithTaxRate19 = await TestDataService.createBasicProduct({}, taxRate19.id);
        const product1WithTaxRate7 = await TestDataService.createBasicProduct({}, taxRate7.id);
        let orderNumber: string;

        await test.step('Add 2 different products with different tax rates to chart, proceed to checkout and validate on confirm page the tax price.', async () => {
            await ShopCustomer.attemptsTo(Login());
            await ShopCustomer.goesTo(StorefrontProductDetail.url(productWithTaxRate19));
            await ShopCustomer.attemptsTo(AddProductToCart(productWithTaxRate19, '2'));
            await ShopCustomer.goesTo(StorefrontProductDetail.url(product1WithTaxRate7));
            await ShopCustomer.attemptsTo(AddProductToCart(product1WithTaxRate7, '2'));
            await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
            await ShopCustomer.expects(StorefrontCheckoutConfirm.taxPrice.nth(0)).toHaveText('€3.19');
            await ShopCustomer.expects(StorefrontCheckoutConfirm.taxPrice.nth(1)).toHaveText('€1.31');
        });

        await test.step('Submit the order, navigate to checkout finish page and validate the tax price.', async () => {
            await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
            await ShopCustomer.attemptsTo(SelectInvoicePaymentOption());
            await ShopCustomer.attemptsTo(SelectStandardShippingOption());

            await ShopCustomer.attemptsTo(SubmitOrder());
            const orderId = StorefrontCheckoutFinish.getOrderId();
            TestDataService.addCreatedRecord('order', orderId);
            orderNumber = await StorefrontCheckoutFinish.getOrderNumber();

            await ShopCustomer.expects(StorefrontCheckoutFinish.taxPrice.nth(0)).toHaveText('€3.19');
            await ShopCustomer.expects(StorefrontCheckoutFinish.taxPrice.nth(1)).toHaveText('€1.31');
        });

        await test.step('Navigate to customers account order page and validate the orders tax price.', async () => {
            await ShopCustomer.goesTo(StorefrontAccountOrder.url());
            const orderLocators = await StorefrontAccountOrder.getOrderByOrderNumber(orderNumber);
            await orderLocators.orderDetailButton.click();
            await ShopCustomer.expects(orderLocators.taxPrice.nth(0)).toHaveText('€3.19');
            await ShopCustomer.expects(orderLocators.taxPrice.nth(1)).toHaveText('€1.31');
        });
    }
);

test(
    'As a customer, I want to perform a checkout process with 0% tax rate.',
    { tag: ['@Checkout'] },
    async ({
        ShopCustomer,
        TestDataService,
        StorefrontProductDetail,
        StorefrontCheckoutConfirm,
        StorefrontCheckoutFinish,
        SelectStandardShippingOption,
        Login,
        AddProductToCart,
        ProceedFromProductToCheckout,
        ConfirmTermsAndConditions,
        SelectInvoicePaymentOption,
        SubmitOrder,
        StorefrontAccountOrder,
    }) => {
        const taxRate0 = await TestDataService.createTaxRate({ taxRate: 0 });
        const productWithTaxRate0 = await TestDataService.createBasicProduct({}, taxRate0.id);
        let orderNumber: string;

        await test.step('Add one product with tax rate to chart, proceed to checkout and validate on confirm page the tax price.', async () => {
            await ShopCustomer.attemptsTo(Login());
            await ShopCustomer.goesTo(StorefrontProductDetail.url(productWithTaxRate0));
            await ShopCustomer.attemptsTo(AddProductToCart(productWithTaxRate0));
            await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
            await ShopCustomer.expects(StorefrontCheckoutConfirm.taxPrice).toHaveText('€0.00');
        });

        await test.step('Submit the order, navigate to checkout finish page and validate the tax price.', async () => {
            await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
            await ShopCustomer.attemptsTo(SelectInvoicePaymentOption());
            await ShopCustomer.attemptsTo(SelectStandardShippingOption());

            await ShopCustomer.attemptsTo(SubmitOrder());
            const orderId = StorefrontCheckoutFinish.getOrderId();
            TestDataService.addCreatedRecord('order', orderId);
            orderNumber = await StorefrontCheckoutFinish.getOrderNumber();

            await ShopCustomer.expects(StorefrontCheckoutFinish.taxPrice).toHaveText('€0.00');
        });

        await test.step('Navigate to customers account order page and validate the orders tax price.', async () => {
            await ShopCustomer.goesTo(StorefrontAccountOrder.url());
            const orderLocators = await StorefrontAccountOrder.getOrderByOrderNumber(orderNumber);
            await orderLocators.orderDetailButton.click();
            await ShopCustomer.expects(orderLocators.taxPrice).toHaveText('€0.00');
        });
    }
);
