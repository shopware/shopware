import { test, expect } from '@fixtures/AcceptanceTest';

test('Visual: Storefront Product Detail Page', { tag: '@Visual' }, async ({
    ShopCustomer,
    TestDataService,
    StorefrontProductDetail,
    StorefrontHome,
    }) => {

    const currency = await TestDataService.getCurrency('EUR');
    const product = await TestDataService.createBasicProduct({
        name: 'Test Product',
        productNumber: 'TEST-123',
        description: null,
        stock: 10,
        price: [
            {
                currencyId: currency.id,
                gross: 10,
                linked: false,
                net: 8.4,
            },
        ],
    });
    await TestDataService.clearCaches();
    await TestDataService.setSystemConfig({ 'core.basicInformation.useDefaultCookieConsent': false });

    await ShopCustomer.expects(async () => {
        await test.step('Wait for products to be visible.', async () => {
            await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
            const productLocator1 = await StorefrontHome.getListingItemByProductName(product.name);
            await ShopCustomer.expects(productLocator1.productName).toBeVisible();
        });
    }).toPass({
        intervals: [1_000, 2_500], // retry after 1 seconds, then every 2.5 seconds
    });
    await test.step('Creates a screenshot of the product detail page General tab.', async () => {
        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
        ShopCustomer.expects(StorefrontProductDetail.addToCartButton).toBeVisible();
        await expect(StorefrontProductDetail.page).toHaveScreenshot('Product-Detail-Page.png', {
            fullPage: true,
        });
    });
});
