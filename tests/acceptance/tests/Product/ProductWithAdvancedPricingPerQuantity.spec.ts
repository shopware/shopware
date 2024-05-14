import { test, expect } from '@fixtures/AcceptanceTest';

test('Journey: Customer gets a special product price depending on the amount of products bought. @journey @checkout', async ({
    shopCustomer,
    checkoutCartPage,
    productData,
    productDetailPage,
    AddProductToCart,
    adminApiContext,
    storeBaseConfig,

    }) => {
    test.info().annotations.push({
        type: 'Description',
        description:
            'This scenario tests if a certain product price, which depends on the quantity bought, can be actualized.',
    });

    const ruleResponse = await adminApiContext.post('./search/rule', {
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

    await expect(ruleResponse.ok()).toBeTruthy();

    const ruleResponseJson = await ruleResponse.json();

    const rule = ruleResponseJson.data[0]

    const priceResponse = await adminApiContext.post('./product-price', {
        data: {
            productId: productData.id,
            ruleId: rule.id,
            price: [{
                currencyId: storeBaseConfig.eurCurrencyId,
                gross: 99.99,
                linked: false,
                net: 84.03,
            }],
            quantityStart: 1,
            quantityEnd: 10,
        },
    });

    await expect(priceResponse.ok()).toBeTruthy();
    const priceResponseAdvanced = await adminApiContext.post('./product-price', {
        data: {
            productId: productData.id,
            ruleId: rule.id,
            price: [{
                currencyId: storeBaseConfig.eurCurrencyId,
                gross: 89.99,
                linked: false,
                net: 75.62,
            }],
            quantityStart: 11,
        },
    });

    await expect(priceResponseAdvanced.ok()).toBeTruthy();

    await shopCustomer.goesTo(productDetailPage);
    await shopCustomer.attemptsTo(AddProductToCart(productData, '12'));
    await shopCustomer.expects(checkoutCartPage.unitPriceInfo).toContainText('€89.99*')

});

