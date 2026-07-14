import { test, translate } from '@fixtures/AcceptanceTest';

test('As a customer, I can see that T&C is auto-confirmed via summary text instead of a checkbox', {
    tag: ['@Checkout', '@Storefront'],
}, async ({
    ShopCustomer,
    TestDataService,
    FeatureService,
    StorefrontProductDetail,
    StorefrontCheckoutConfirm,
    StorefrontCheckoutFinish,
    Login,
    AddProductToCart,
    ProceedFromProductToCheckout,
    SelectPaymentMethod,
    SelectShippingMethod,
    SubmitOrder,
}) => {
    // Skip entirely unless 6.8 behavior exists.
    test.skip(!(await FeatureService.isEnabled('V6_8_0_0')), 'v6.8.0.0 only');
    // Make sure that config to show T&C checkbox is disabled
    await TestDataService.setSystemConfig({'core.cart.showTosCheckbox': false});

    const product = await TestDataService.createBasicProduct();
    await ShopCustomer.attemptsTo(Login());
    await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
    await ShopCustomer.attemptsTo(AddProductToCart(product));
    await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
    // Verify that Terms and Conditions and cancellation policy text display
    await ShopCustomer.expects(StorefrontCheckoutConfirm.termsAndConditionsCheckbox).toBeHidden();
    await ShopCustomer.expects(StorefrontCheckoutConfirm.termsAutoConfirmedText).toContainText(translate('storefront:checkout:confirm.autoConfirmTermsText'));
    await ShopCustomer.expects(StorefrontCheckoutConfirm.termsAutoConfirmedText.getByRole('button')).toHaveCount(2);
    // Verify the order still goes through without ticking anything.
    await ShopCustomer.attemptsTo(SelectPaymentMethod('Invoice'));
    await ShopCustomer.attemptsTo(SelectShippingMethod('Standard'));
    await ShopCustomer.attemptsTo(SubmitOrder());
    await ShopCustomer.expects(StorefrontCheckoutFinish.headline).toBeVisible();
});