import { test, expect } from '@fixtures/AcceptanceTest';

test('Visual: Storefront Product Detail Page', { tag: '@Visual' }, async ({
    ShopCustomer,
    TestDataService,
    StorefrontProductDetail,
    CheckVisibilityInHome,
    }) => {

    const product = await TestDataService.createBasicProduct({
        name: 'TestProduct',
        productNumber: '123',
    });
    await TestDataService.setSystemConfig({ 'core.basicInformation.useDefaultCookieConsent': false });

    await test.step('Creates a screenshot of the product detail page General tab.', async () => {
        await CheckVisibilityInHome(product.name);
        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
        ShopCustomer.expects(StorefrontProductDetail.addToCartButton).toBeVisible();
        await expect(StorefrontProductDetail.page).toHaveScreenshot('Product-Detail-Page.png', {
            fullPage: true,
        });
    });
});
