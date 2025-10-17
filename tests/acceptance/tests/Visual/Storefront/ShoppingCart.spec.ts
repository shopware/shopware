import { test, expect } from '@fixtures/AcceptanceTest';
import { replaceElements } from '@shopware-ag/acceptance-test-suite';

test('Visual: Storefront Shopping Cart.', { tag: '@Visual' }, async ({
    AddProductToCart,
    ShopCustomer,
    StorefrontProductDetail,
    StorefrontOffCanvasCart,
    StorefrontCheckoutCart,
    TestDataService,
}) => {
    const product = await TestDataService.createBasicProduct();
    await TestDataService.setSystemConfig({ 'core.basicInformation.useDefaultCookieConsent': false });

    await test.step('Creates a screenshot of off-canvas shopping cart in storefront.', async () => {
        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
        await ShopCustomer.attemptsTo(AddProductToCart(product));
        await ShopCustomer.expects(StorefrontOffCanvasCart.goToCartButton).toBeVisible();

        await replaceElements(StorefrontProductDetail.page, [
            StorefrontProductDetail.productName,
            StorefrontProductDetail.productDescriptionTitle,
            StorefrontProductDetail.offCanvasLineItemLabel,
            StorefrontProductDetail.offCanvasLineItemProductNumber,
            StorefrontProductDetail.offCanvasLineItemDeliveryDate,
        ]);

        await StorefrontProductDetail.page.setViewportSize({ width: 1440, height: 800 });

        await expect(StorefrontProductDetail.page.locator('.offcanvas')).toHaveScreenshot('OffcanvasCart.png');
    });

    await test.step('Creates a screenshot of shopping cart (checkout/cart) in storefront.', async () => {
        await StorefrontOffCanvasCart.goToCartButton.click();
        await ShopCustomer.expects(StorefrontCheckoutCart.goToCheckoutButton).toBeVisible();

        await replaceElements(StorefrontCheckoutCart.page, [
            StorefrontCheckoutCart.productNameLabel,
            StorefrontCheckoutCart.productNumberLabel,
            StorefrontCheckoutCart.productDeliveryDateLabel,
        ]);

        await expect(StorefrontCheckoutCart.page).toHaveScreenshot('CheckoutCart.png', {
            fullPage: true,
        });
    });
});