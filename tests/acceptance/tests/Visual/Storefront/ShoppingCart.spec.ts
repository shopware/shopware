import { test, expect } from '@fixtures/AcceptanceTest';
import { replaceElementsIndividually } from '@shopware-ag/acceptance-test-suite';

test('Visual: Storefront Shopping Cart.', { tag: '@Visual' }, async ({
    AddProductToCart,
    ShopCustomer,
    StorefrontProductDetail,
    StorefrontOffCanvasCart,
    StorefrontCheckoutCart,
    TestDataService,
}) => {
    const product = await TestDataService.createBasicProduct({
        name: 'TestProduct',
        productNumber: '123',
    });

    await TestDataService.setSystemConfig({ 'core.basicInformation.useDefaultCookieConsent': false });

    await test.step('Creates a screenshot of off-canvas shopping cart in storefront.', async () => {
        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
        await ShopCustomer.attemptsTo(AddProductToCart(product));
        await ShopCustomer.expects(StorefrontOffCanvasCart.goToCartButton).toBeVisible();

        await replaceElementsIndividually(StorefrontCheckoutCart.page, [
            {selector: StorefrontProductDetail.offCanvasLineItemDeliveryDate, replaceWith: 'Delivery period: 01/01/1970 - 03/01/1970'},
        ]);

        await StorefrontProductDetail.page.setViewportSize({ width: 1440, height: 800 });

        await expect(StorefrontProductDetail.page.locator('.offcanvas')).toHaveScreenshot('OffcanvasCart.png');
    });

    await test.step('Creates a screenshot of shopping cart (checkout/cart) in storefront.', async () => {
        await StorefrontOffCanvasCart.goToCartButton.click();
        await ShopCustomer.expects(StorefrontCheckoutCart.goToCheckoutButton).toBeVisible();

        await replaceElementsIndividually(StorefrontCheckoutCart.page, [
            {selector: StorefrontCheckoutCart.productDeliveryDateLabel, replaceWith: 'Delivery period: 01/01/1970 - 03/01/1970'},
        ]);

        await expect(StorefrontCheckoutCart.page).toHaveScreenshot('CheckoutCart.png', {
            fullPage: true,
        });
    });
});