import { test, expect } from '@fixtures/AcceptanceTest';
import path from 'path';

test('Visual: Final order page in the Storefront.', { tag: '@Visual' }, async ({
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
}) => {
    const product = await TestDataService.createBasicProduct();

    await ShopCustomer.attemptsTo(Login());

    await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
    await ShopCustomer.attemptsTo(AddProductToCart(product));
    await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
    await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
    await ShopCustomer.attemptsTo(SelectInvoicePaymentOption());
    await ShopCustomer.attemptsTo(SelectStandardShippingOption());
    await ShopCustomer.attemptsTo(SubmitOrder());

    const orderId = StorefrontCheckoutFinish.getOrderId();
    TestDataService.addCreatedRecord('order', orderId);

    await test.step('Creates a screenshot and compare it on final order page in storefront.', async () => {

        await StorefrontCheckoutFinish.page.setViewportSize({ width: 1280, height: 1111});

        await expect(StorefrontCheckoutFinish.page).toHaveScreenshot({
            maxDiffPixelRatio: 0.2,
            stylePath: path.resolve('./tests/Visual/screenshot.css'),
            mask: [
                StorefrontCheckoutFinish.page.locator('.finish-ordernumber'),
            ],
        });
    });
});
