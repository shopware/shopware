import { test, expect } from '@fixtures/AcceptanceTest';

test('Journey: Customer gets a special product price depending on rules. @journey @checkout', async ({
    ShopCustomer,
    AdminApiContext,
    SalesChannelBaseConfig,
    ProductData,
    StorefrontProductDetail,
    AddProductToCart,
}) => {

    const response = await AdminApiContext.post('./search/rule', {
        data: {
            limit: 1,
            filter: [
                {
                    type: 'equals',
                    field: 'name',
                    value: 'Cart >= 0',
                },
            ],
        },
    });

    const responseJson = await response.json();

    const rule = responseJson.data[0]

    const priceResponse = await AdminApiContext.post('./product-price', {
        data: {
            productId: ProductData.id,
            ruleId: rule.id,
            price: [{
                currencyId: SalesChannelBaseConfig.eurCurrencyId,
                gross: 99.99,
                linked: false,
                net: 93.45,
            }],
            quantityStart: 1,

        },
    });

    expect(priceResponse.ok()).toBeTruthy();

    await ShopCustomer.goesTo(StorefrontProductDetail);
    await ShopCustomer.expects(StorefrontProductDetail.page).toHaveTitle(
        `${ProductData.translated.name} | ${ProductData.productNumber}`
    );
    await ShopCustomer.attemptsTo(AddProductToCart(ProductData));
    await ShopCustomer.expects(StorefrontProductDetail.offCanvasSummaryTotalPrice).toHaveText('€99.99*');
});

