import {
    test,
    PropertyGroup,
    getCurrencySymbolFromLocale,
    getLocale,
    getCurrencyCodeFromLocale,
} from '@fixtures/AcceptanceTest';

test(
    'As a customer, I should see the correct listing price and normal price for variant products with differing prices.',
    {
        tag: ['@Product, @Variant', '@Storefront'],
    },
    async ({ ShopCustomer, TestDataService, StorefrontHome, StorefrontProductDetail, SalesChannelBaseConfig }) => {
        const currency = await TestDataService.getCurrency(getCurrencyCodeFromLocale(getLocale()));
        const prices = [
            {
                currencyId: currency.id,
                gross: 10,
                linked: false,
                net: 8.4,
                listPrice: {
                    currencyId: currency.id,
                    gross: 20,
                    linked: false,
                    net: 16.8,
                },
                percentage: {
                    gross: 50,
                    net: 50,
                },
            },
            {
                currencyId: SalesChannelBaseConfig.defaultCurrencyId,
                gross: 10,
                linked: false,
                net: 8.4,
            },
        ];

        const parentProduct = await TestDataService.createBasicProduct({
            price: prices,
            variantListingConfig: { displayParent: true },
        });
        const propertyGroupColor = await TestDataService.createColorPropertyGroup();
        const propertyGroups: PropertyGroup[] = [];
        propertyGroups.push(propertyGroupColor);
        const variantProducts = await TestDataService.createVariantProducts(parentProduct, propertyGroups, {
            price: prices,
        });
        const productItemLocators = await StorefrontHome.getListingItemByProductName(parentProduct.name);
        const currencyIcon = getCurrencySymbolFromLocale(getLocale());
        await test.step('Validating listing price is available on product listing page for base variant product.', async () => {
            await ShopCustomer.goesTo(StorefrontHome.url());
            await ShopCustomer.expects(productItemLocators.productPrice).toContainText(`${currencyIcon}10.00`);
            await ShopCustomer.expects(productItemLocators.productListingPrice).toContainText(`${currencyIcon}20.00`);
            await ShopCustomer.expects(productItemLocators.productListingPricePercentage).toContainText('(50% saved)');
            await ShopCustomer.expects(productItemLocators.productListingPriceBadge).toContainText('%');
        });

        await test.step('Validating listing price is available for each variant product.', async () => {
            for (const variantProduct of variantProducts) {
                await ShopCustomer.goesTo(StorefrontProductDetail.url(variantProduct));
                await ShopCustomer.expects(StorefrontProductDetail.productSinglePrice).toContainText(
                    `${currencyIcon}10.00`
                );
                await ShopCustomer.expects(StorefrontProductDetail.productListingPriceBadge).toContainText('%');
                await ShopCustomer.expects(StorefrontProductDetail.productListingPrice).toContainText(
                    `${currencyIcon}20.00`
                );
                await ShopCustomer.expects(StorefrontProductDetail.productListingPricePercentage).toContainText(
                    '(50% saved)'
                );
            }
        });
    }
);
