import { test, expect } from '@fixtures/AcceptanceTest';
import { hideElements, replaceElements } from '@shopware-ag/acceptance-test-suite';

test('Visual: Storefront Shopping Cart.', { tag: '@Visual' }, async ({
    AddProductToCart,
    ShopCustomer,
    StorefrontProductDetail,
    StorefrontOffCanvasCart,
    StorefrontCheckoutCart,
    TestDataService,
}) => {
    const product = await TestDataService.createBasicProduct();

    await test.step('Creates a screenshot of off-canvas shopping cart in storefront.', async () => {
        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
        await ShopCustomer.attemptsTo(AddProductToCart(product));
        await ShopCustomer.expects(StorefrontOffCanvasCart.goToCartButton).toBeVisible();

        await hideElements(StorefrontProductDetail.page, [
            '.cookie-permission-container',
        ]);

        await replaceElements(StorefrontProductDetail.page, [
            '.product-detail-name',
            '.product-detail-description-title',
            '.line-item-label',
            '.line-item-product-number',
            '.line-item-delivery-date',
        ]);

        await StorefrontProductDetail.page.setViewportSize({ width: 1440, height: 800 });

        await expect(StorefrontProductDetail.page.locator('.offcanvas')).toHaveScreenshot('OffcanvasCart.png', {});
    });

    await test.step('Creates a screenshot of shopping cart (checkout/cart) in storefront.', async () => {
        await StorefrontOffCanvasCart.goToCartButton.click();
        await ShopCustomer.expects(StorefrontCheckoutCart.goToCheckoutButton).toBeVisible();

        await hideElements(StorefrontCheckoutCart.page, [
            '.cookie-permission-container',
        ]);

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