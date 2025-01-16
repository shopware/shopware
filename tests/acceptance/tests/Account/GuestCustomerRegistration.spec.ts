import { test } from '@fixtures/AcceptanceTest';

test('Guest customer must be able to register in the Storefront.', { tag: '@Registration' }, async ({
    ShopCustomer,
    StorefrontCheckoutConfirm,
    StorefrontCheckoutFinish,
    StorefrontCheckoutCart,
    StorefrontProductDetail,
    AddProductToCart,
    ConfirmTermsAndConditions,
    SelectInvoicePaymentOption,
    SelectStandardShippingOption,
    SubmitOrder,
    TestDataService,
    Register,
}) => {
    const product = await TestDataService.createBasicProduct();

    await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
    await ShopCustomer.expects(StorefrontProductDetail.page).toHaveTitle(`${ product.translated.name } | ${ product.productNumber }`);
    await ShopCustomer.attemptsTo(AddProductToCart(product));
    await StorefrontCheckoutCart.goToCheckoutButton.click();

    await ShopCustomer.attemptsTo(Register({ isGuest: true }));

    await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
    await ShopCustomer.attemptsTo(SelectInvoicePaymentOption());
    await ShopCustomer.attemptsTo(SelectStandardShippingOption());
    await ShopCustomer.expects(StorefrontCheckoutConfirm.grandTotalPrice).toHaveText('€10.00*');
    await ShopCustomer.attemptsTo(SubmitOrder());
    await ShopCustomer.expects(StorefrontCheckoutFinish.grandTotalPrice).toHaveText('€10.00*');

    const orderId = StorefrontCheckoutFinish.getOrderId();
    TestDataService.addCreatedRecord('order', orderId);
});

test('Guest commercial customer must be able to register in the Storefront.', { tag: '@Registration' }, async ({
    ShopCustomer,
    StorefrontCheckoutConfirm,
    StorefrontCheckoutFinish,
    StorefrontCheckoutCart,
    StorefrontProductDetail,
    AddProductToCart,
    ConfirmTermsAndConditions,
    SelectInvoicePaymentOption,
    SelectStandardShippingOption,
    SubmitOrder,
    TestDataService,
    Register,
}) => {
    await TestDataService.setSystemConfig({ 'core.loginRegistration.showAccountTypeSelection': true });
    const product = await TestDataService.createBasicProduct();

    await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
    await ShopCustomer.expects(StorefrontProductDetail.page).toHaveTitle(`${ product.translated.name } | ${ product.productNumber }`);
    await ShopCustomer.attemptsTo(AddProductToCart(product));
    await StorefrontCheckoutCart.goToCheckoutButton.click();

    await ShopCustomer.attemptsTo(Register({ isCommercial: true, isGuest: true }));

    await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
    await ShopCustomer.attemptsTo(SelectInvoicePaymentOption());
    await ShopCustomer.attemptsTo(SelectStandardShippingOption());
    await ShopCustomer.expects(StorefrontCheckoutConfirm.grandTotalPrice).toHaveText('€10.00*');
    await ShopCustomer.attemptsTo(SubmitOrder());
    await ShopCustomer.expects(StorefrontCheckoutFinish.grandTotalPrice).toHaveText('€10.00*');

    const orderId = StorefrontCheckoutFinish.getOrderId();
    TestDataService.addCreatedRecord('order', orderId);
});
