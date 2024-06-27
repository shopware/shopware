import { test, expect } from '@fixtures/AcceptanceTest';

test('Customer gets a special product price depending on rules.', {
    tag: ['@Product', '@Prices', '@Rules'],
}, async ({
    ShopCustomer,
    TestDataService,
    AdminApiContext,
    SalesChannelBaseConfig,
    StorefrontProductDetail,
    AddProductToCart,
}) => {

    const product = await TestDataService.createBasicProduct();
    const rule = await TestDataService.getRule('Always valid (Default)');

    const priceResponse = await AdminApiContext.post('./product-price', {
        data: {
            productId: product.id,
            ruleId: rule.id,
            price: [{
                currencyId: SalesChannelBaseConfig.eurCurrencyId,
                gross: 99.99,
                linked: false,
                net: 93.45,
            }, {
                currencyId: SalesChannelBaseConfig.defaultCurrencyId,
                gross: 99.99,
                linked: false,
                net: 93.45,
            }],
            quantityStart: 1,
        },
    });

    expect(priceResponse.ok()).toBeTruthy();

    await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
    await ShopCustomer.attemptsTo(AddProductToCart(product));
    await ShopCustomer.expects(StorefrontProductDetail.offCanvasSummaryTotalPrice).toHaveText('€99.99*');
});
