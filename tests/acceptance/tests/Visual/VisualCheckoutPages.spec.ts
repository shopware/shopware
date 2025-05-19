import { test, expect } from '@fixtures/AcceptanceTest';
import path from 'path';

test('Visual: storefront:checkout/finish.', { tag: '@Visual' }, async ({
    ShopCustomer,
    TestDataService,
    StorefrontProductDetail,
    Login,
    AddProductToCart,
    ProceedFromProductToCheckout,
    ConfirmTermsAndConditions,
    SelectInvoicePaymentOption,
    SelectStandardShippingOption,
    SubmitOrder,
    StorefrontCheckoutFinish,
    StorefrontCheckoutConfirm,
    StorefrontCheckoutRegister,
}) => {
    const product = await TestDataService.createBasicProduct();

    await test.step('Create screenshot of checkout/register page in storefront.', async () => {
        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
        await ShopCustomer.attemptsTo(AddProductToCart(product));
        await StorefrontProductDetail.offCanvasCartGoToCheckoutButton.click();
        await ShopCustomer.expects(StorefrontCheckoutRegister.cartLineItemImages).toBeVisible();

        await StorefrontCheckoutFinish.page.setViewportSize({ width: 1280, height: 1440});

        await expect(StorefrontCheckoutRegister.page).toHaveScreenshot({
            stylePath: path.resolve('./tests/Visual/screenshot.css'),
        });
    });

    await test.step('Create screenshot of checkout/confirm page in storefront.', async () => {
        await ShopCustomer.attemptsTo(Login());
        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
        await ShopCustomer.attemptsTo(AddProductToCart(product));
        await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
        await StorefrontCheckoutFinish.page.setViewportSize({ width: 1280, height: 1640});

        await expect(StorefrontCheckoutConfirm.page).toHaveScreenshot({
            stylePath: path.resolve('./tests/Visual/screenshot.css'),
        });
    });

    await test.step('Create screenshot of checkout/finish page in storefront.', async () => {
        await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
        await ShopCustomer.attemptsTo(SelectInvoicePaymentOption());
        await ShopCustomer.attemptsTo(SelectStandardShippingOption());
        await ShopCustomer.attemptsTo(SubmitOrder());
        const orderId = StorefrontCheckoutFinish.getOrderId();
        TestDataService.addCreatedRecord('order', orderId);

        await StorefrontCheckoutFinish.page.setViewportSize({ width: 1280, height: 1440});

        await expect(StorefrontCheckoutFinish.page).toHaveScreenshot({
            // maxDiffPixelRatio: 0.2,
            stylePath: path.resolve('./tests/Visual/screenshot.css'),
            mask: [
                StorefrontCheckoutFinish.page.locator('.finish-ordernumber'),
            ],
        });
    });
});
