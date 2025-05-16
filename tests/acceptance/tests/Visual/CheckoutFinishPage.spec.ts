import { test, expect } from '@fixtures/AcceptanceTest';
import path from 'path';

test('Visual: Storefront checkout/finish page.', { tag: '@Visual' }, async ({
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

    await test.step('Create a screenshot from checkout/finish page in storefront.', async () => {

        await StorefrontCheckoutFinish.page.setViewportSize({ width: 1280, height: 1111});

        await expect(StorefrontCheckoutFinish.page).toHaveScreenshot({
            stylePath: path.resolve('./tests/Visual/screenshot.css'),
            mask: [
                StorefrontCheckoutFinish.page.locator('.finish-ordernumber'),
            ],
        });
    });
});
