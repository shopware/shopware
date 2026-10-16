import { test, getCurrencyCodeFromLocale, formatPrice } from '@fixtures/AcceptanceTest';

test(
    'As a customer, I should see savings calculated against the lowest price of the last 30 days instead of the list price.',
    {
        tag: [
            '@Product',
            '@Storefront',
        ],
    },
    async ({ ShopCustomer, TestDataService, StorefrontHome, StorefrontProductDetail, SalesChannelBaseConfig }) => {
        const currency = await TestDataService.getCurrency(getCurrencyCodeFromLocale());
        const getPrices = (unitPrice: number) =>
            [currency.id, SalesChannelBaseConfig.defaultCurrencyId].map((currencyId) => ({
                currencyId,
                gross: unitPrice,
                linked: false,
                net: unitPrice,
                listPrice: { currencyId, gross: 100, linked: false, net: 100 },
                regulationPrice: { currencyId, gross: 80, linked: false, net: 80 },
            }));

        // 1 - 75 / 80 = 6.25% saved against the lowest price of the last 30 days, the list price of 100 is not the reference.
        const reducedProduct = await TestDataService.createBasicProduct({ price: getPrices(75) });
        // Above the lowest price of the last 30 days there is no saving to announce, although the list price is higher.
        const raisedProduct = await TestDataService.createBasicProduct({ price: getPrices(85) });

        await TestDataService.clearCaches();

        await test.step('The product detail page shows the saving against the lowest price of the last 30 days.', async () => {
            await ShopCustomer.goesTo(StorefrontProductDetail.url(reducedProduct));
            await ShopCustomer.expects(StorefrontProductDetail.productSinglePrice).toContainText(formatPrice(75.0));
            await ShopCustomer.expects(StorefrontProductDetail.productListingPrice).not.toBeVisible();
            await ShopCustomer.expects(StorefrontProductDetail.page.locator('.regulation-price')).toContainText(
                `Lowest price (last 30 days): ${formatPrice(80.0)}`,
            );
            await ShopCustomer.expects(StorefrontProductDetail.productListingPricePercentage).toContainText('(6.25% saved)');
            await ShopCustomer.expects(StorefrontProductDetail.productListingPriceBadge).toBeVisible();
        });

        await test.step('The product detail page announces no saving above the lowest price of the last 30 days.', async () => {
            await ShopCustomer.goesTo(StorefrontProductDetail.url(raisedProduct));
            await ShopCustomer.expects(StorefrontProductDetail.productSinglePrice).toContainText(formatPrice(85.0));
            await ShopCustomer.expects(StorefrontProductDetail.productListingPrice).not.toBeVisible();
            await ShopCustomer.expects(StorefrontProductDetail.page.locator('.regulation-price')).toContainText(
                `Lowest price (last 30 days): ${formatPrice(80.0)}`,
            );
            await ShopCustomer.expects(StorefrontProductDetail.productListingPricePercentage).not.toBeVisible();
            await ShopCustomer.expects(StorefrontProductDetail.productListingPriceBadge).not.toBeVisible();
        });

        await test.step('The listing shows a discount only for the saving against the lowest price of the last 30 days.', async () => {
            await ShopCustomer.goesTo(StorefrontHome.url());

            const reducedItem = await StorefrontHome.getListingItemByProductName(reducedProduct.name);
            await ShopCustomer.expects(reducedItem.productListingPrice).not.toBeVisible();
            await ShopCustomer.expects(reducedItem.productListingPricePercentage).toContainText('(6.25% saved)');
            await ShopCustomer.expects(reducedItem.productListingPriceBadge).toBeVisible();

            const raisedItem = await StorefrontHome.getListingItemByProductName(raisedProduct.name);
            await ShopCustomer.expects(raisedItem.productListingPrice).not.toBeVisible();
            await ShopCustomer.expects(raisedItem.productListingPricePercentage).not.toBeVisible();
            await ShopCustomer.expects(raisedItem.productListingPriceBadge).not.toBeVisible();
        });
    },
);
