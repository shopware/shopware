import { test, expect } from '@fixtures/AcceptanceTest';

test('Journey: Customer gets a special product price depending on the amount of products bought. @journey @checkout', async ({
    ShopCustomer,
    AdminApiContext,
    SalesChannelBaseConfig,
    ProductData,
    StorefrontCheckoutCart,
    StorefrontProductDetail,
    AddProductToCart,
}) => {

    const ruleResponse = await AdminApiContext.post('./search/rule', {
        data: {
            limit: 1,
            filter: [{
                type: 'equals',
                field: 'name',
                value: 'Always valid (Default)',
            }],
        },
    });
    expect(ruleResponse.ok()).toBeTruthy();

    const ruleResponseJson = await ruleResponse.json();

    const rule = ruleResponseJson.data[0]

    const priceResponse = await AdminApiContext.post('./product-price', {
        data: {
            productId: ProductData.id,
            ruleId: rule.id,
            price: [{
                currencyId: SalesChannelBaseConfig.eurCurrencyId,
                gross: 99.99,
                linked: false,
                net: 84.03,
            },
            {
                currencyId: SalesChannelBaseConfig.defaultCurrencyId,
                gross: 99.99,
                linked: false,
                net: 84.03,
            }],
            quantityStart: 1,
            quantityEnd: 10,
        },
    });
    expect(priceResponse.ok()).toBeTruthy();

    const priceResponseAdvanced = await AdminApiContext.post('./product-price', {
        data: {
            productId: ProductData.id,
            ruleId: rule.id,
            price: [{
                currencyId: SalesChannelBaseConfig.eurCurrencyId,
                gross: 89.99,
                linked: false,
                net: 75.62,
            },
            {
                currencyId: SalesChannelBaseConfig.defaultCurrencyId,
                gross: 89.99,
                linked: false,
                net: 75.62,
            }],
            quantityStart: 11,
        },
    });
    expect(priceResponseAdvanced.ok()).toBeTruthy();

    await ShopCustomer.goesTo(StorefrontProductDetail);
    await ShopCustomer.attemptsTo(AddProductToCart(ProductData, '12'));
    await ShopCustomer.expects(StorefrontCheckoutCart.unitPriceInfo).toContainText('€89.99*')
});
