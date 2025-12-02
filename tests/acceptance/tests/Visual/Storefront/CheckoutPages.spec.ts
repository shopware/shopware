import { test, expect } from '@fixtures/AcceptanceTest';
import { hideElements, replaceElements } from '@shopware-ag/acceptance-test-suite';


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
        
        await hideElements(StorefrontCheckoutRegister.page, [
            '.cookie-permission-container',
        ]);
        await replaceElements(StorefrontCheckoutRegister.page, [
            '.line-item-label',
            '.line-item-product-number',
            '.line-item-delivery-date',
        ]);

        await expect(StorefrontCheckoutRegister.page).toHaveScreenshot('Checkout-Register.png', {
            fullPage: true,
        });
    });

    await test.step('Create screenshot of checkout/confirm page in storefront.', async () => {
        await ShopCustomer.attemptsTo(Login());
        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
        await ShopCustomer.attemptsTo(AddProductToCart(product));
        await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
        
        await hideElements(StorefrontCheckoutConfirm.page, [
            '.cookie-permission-container',
        ]);

        await replaceElements(StorefrontCheckoutConfirm.page, [
            '.line-item-label',
            '.line-item-product-number',
            '.line-item-delivery-date',
            '.confirm-address-shipping',
            '.confirm-address-billing',
        ]);

        await expect(StorefrontCheckoutConfirm.page).toHaveScreenshot('Checkout-Confirm.png', {
            fullPage: true,
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
        
        await hideElements(StorefrontCheckoutFinish.page, [
            '.cookie-permission-container',
        ]);
        await replaceElements(StorefrontCheckoutFinish.page, [
            '.line-item-label',
            '.line-item-product-number',
            '.line-item-delivery-date',
            '.finish-ordernumber',
            '.finish-address-shipping',
            '.finish-address-billing',
        ]);

        await expect(StorefrontCheckoutFinish.page).toHaveScreenshot('Checkout-Finish.png', {
            fullPage: true,
        });
    });
});
