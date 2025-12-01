import { test, expect, formatPrice } from '@fixtures/AcceptanceTest';

test(
    'Customer gets a special product price depending on rules.',
    {
        tag: ['@Product', '@Prices', '@Rules', '@Storefront'],
    },
    async ({
        ShopCustomer,
        TestDataService,
        AdminApiContext,
        SalesChannelBaseConfig,
        StorefrontProductDetail,
        AddProductToCart,
    }) => {
        const product = await TestDataService.createBasicProduct();
        const rule = await TestDataService.createBasicRule();
        const priceResponse = await AdminApiContext.post('./product-price', {
            data: {
                productId: product.id,
                ruleId: rule.id,
                price: [
                    {
                        currencyId: SalesChannelBaseConfig.defaultCurrencyId,
                        gross: 8.99,
                        linked: false,
                        net: 7.55,
                    },
                    {
                        currencyId: SalesChannelBaseConfig.currentCurrencyId,
                        gross: 8.99,
                        linked: false,
                        net: 7.55,
                    },
                ],
                quantityStart: 1,
            },
        });
        expect(priceResponse.ok()).toBeTruthy();

        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
        await ShopCustomer.expects(StorefrontProductDetail.productSinglePrice).toContainText(formatPrice(10.0));
        await ShopCustomer.attemptsTo(AddProductToCart(product));
        await ShopCustomer.expects(StorefrontProductDetail.offCanvasSummaryTotalPrice).toContainText(formatPrice(8.99));
    }
);
