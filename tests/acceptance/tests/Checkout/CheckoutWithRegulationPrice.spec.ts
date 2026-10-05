import { test, expect, formatPrice, getCurrencyCodeFromLocale } from '@fixtures/AcceptanceTest';

test(
    'Registered shop customer buys a product with a lowest price of the last 30 days.',
    {
        tag: [
            '@Checkout',
            '@Storefront',
        ],
    },
    async ({
        ShopCustomer,
        TestDataService,
        SalesChannelBaseConfig,
        AdminApiContext,
        StorefrontProductDetail,
        StorefrontCheckoutFinish,
        Login,
        AddProductToCart,
        ProceedFromProductToCheckout,
        ConfirmTermsAndConditions,
        SelectPaymentMethod,
        SelectShippingMethod,
        SubmitOrder,
    }) => {
        const currency = await TestDataService.getCurrency(getCurrencyCodeFromLocale());
        const product = await TestDataService.createBasicProduct({
            price: [currency.id, SalesChannelBaseConfig.defaultCurrencyId].map((currencyId) => ({
                currencyId,
                gross: 75,
                linked: false,
                net: 75,
                listPrice: { currencyId, gross: 100, linked: false, net: 100 },
                regulationPrice: { currencyId, gross: 80, linked: false, net: 80 },
            })),
        });

        await ShopCustomer.attemptsTo(Login());
        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
        await ShopCustomer.attemptsTo(AddProductToCart(product));
        await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
        await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
        await ShopCustomer.attemptsTo(SelectPaymentMethod('Invoice'));
        await ShopCustomer.attemptsTo(SelectShippingMethod('Standard'));
        await ShopCustomer.attemptsTo(SubmitOrder());
        await ShopCustomer.expects(StorefrontCheckoutFinish.grandTotalPrice).toContainText(formatPrice(75.0));

        const orderId = StorefrontCheckoutFinish.getOrderId();
        TestDataService.addCreatedRecord('order', orderId);

        await test.step('The order line item keeps the lowest price of the last 30 days and its saving.', async () => {
            const response = await AdminApiContext.post('search/order-line-item', {
                data: { filter: [{ type: 'equals', field: 'orderId', value: orderId }] },
            });
            expect(response.ok()).toBeTruthy();

            const { data } = await response.json();
            expect(data[0].price.regulationPrice).toEqual(expect.objectContaining({ price: 80, percentage: 6.25 }));
        });
    },
);
