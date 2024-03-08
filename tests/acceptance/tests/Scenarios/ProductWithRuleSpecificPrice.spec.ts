import { test, expect } from '@fixtures/AcceptanceTest';

test('Journey: Customer gets a special product price depending on rules. @journey @checkout', async ({
    shopCustomer,
    productData,
    productDetailPage,
    AddProductToCart,
    adminApiContext,
    storeBaseConfig,

}) => {
    test.info().annotations.push({
        type: 'Description',
        description:
            'This scenario tests if a certain product price can be realised with the help of a default rule.',
    });

    const response = await adminApiContext.post('./search/rule', {
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

    const priceResponse = await adminApiContext.post('./product-price', {
        data: {
            productId: productData.id,
            ruleId: rule.id,
            price: [{
                currencyId: storeBaseConfig.eurCurrencyId,
                gross: 99.99,
                linked: false,
                net: 93.45,
            }],
            quantityStart: 1,

        },
    });

    await expect(priceResponse.ok()).toBeTruthy();

    await shopCustomer.goesTo(productDetailPage);
    await shopCustomer.expects(productDetailPage.page).toHaveTitle(
        `${productData.translated.name} | ${productData.productNumber}`
    );
    await shopCustomer.attemptsTo(AddProductToCart(productData));
    await shopCustomer.expects(productDetailPage.offCanvasSummaryTotalPrice).toHaveText('€99.99*');
});

