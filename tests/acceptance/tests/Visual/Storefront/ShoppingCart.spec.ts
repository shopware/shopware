import { test, expect } from '@fixtures/AcceptanceTest';
import { hideElements, replaceElements } from '@shopware-ag/acceptance-test-suite';

test('Visual: Storefront Shopping Cart.', { tag: '@Visual' }, async ({
    ShopCustomer,
    TestDataService,
    StorefrontProductDetail,
    AddProductToCart,
    StorefrontOffCanvasCart,
    StorefrontCheckoutCart,
}) => {
    const product = await TestDataService.createBasicProduct();
    await TestDataService.setSystemConfig({ 'core.basicInformation.useDefaultCookieConsent': false });

    await test.step('Creates a screenshot of off-canvas shopping cart in storefront.', async () => {
        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
        await ShopCustomer.attemptsTo(AddProductToCart(product));
        await ShopCustomer.expects(StorefrontOffCanvasCart.headline).toBeVisible();

        await replaceElements(StorefrontOffCanvasCart.page, [
            '.product-detail-name',
            '.product-detail-description-title',
            '.line-item-label',
            '.line-item-product-number',
            '.line-item-delivery-date',
        ]);

        await expect(StorefrontOffCanvasCart.page).toHaveScreenshot('OffcanvasCart.png', {
            fullPage: true,
        });
    });

    await test.step('Creates a screenshot of shopping cart (checkout/cart) in storefront.', async () => {
        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
        await ShopCustomer.attemptsTo(AddProductToCart(product));
        await ShopCustomer.expects(StorefrontOffCanvasCart.headline).toBeVisible();
        await StorefrontOffCanvasCart.goToCartButton.click();

        await replaceElements(StorefrontCheckoutCart.page, [
            '.line-item-label',
            '.line-item-product-number',
            '.line-item-delivery-date',
        ]);

        await expect(StorefrontCheckoutCart.page).toHaveScreenshot('CheckoutCart.png', {
            fullPage: true,
        });
    });
});
