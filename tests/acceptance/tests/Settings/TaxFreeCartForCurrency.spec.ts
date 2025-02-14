import { test } from '@fixtures/AcceptanceTest';

test(
    'As a merchant, I would be able to adjust free tax for defined currency.', { tag: '@Settings' }, async ({
    ShopCustomer,
    TestDataService,
    DefaultSalesChannel,
    StorefrontProductDetail,
    StorefrontCheckoutConfirm,
    StorefrontCheckoutFinish,
    ChangeStorefrontCurrency,
    Login,
    AddProductToCart,
    ProceedFromProductToCheckout,
    ConfirmTermsAndConditions,
    SelectInvoicePaymentOption,
    SelectStandardShippingOption,
    SubmitOrder,
}) => {
    const product = await TestDataService.createBasicProduct();
    const currency = await TestDataService.createCurrency({ taxFreeFrom: 5 });
    const customer = await TestDataService.createCustomer();
    await TestDataService.assignSalesChannelCurrency(DefaultSalesChannel.salesChannel.id, currency.id);

    await ShopCustomer.attemptsTo(Login(customer));

    await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
    await ShopCustomer.attemptsTo(ChangeStorefrontCurrency(currency.isoCode));

    // eslint-disable-next-line playwright/no-conditional-in-test
    const isAccessibilityTweaksEnabled = InstanceMeta.features['ACCESSIBILITY_TWEAKS'] || false;

    // eslint-disable-next-line playwright/no-conditional-in-test
    const expectedSuffix = isAccessibilityTweaksEnabled ? '' : '*';
    const productPrice = `${currency.isoCode} 24.00${expectedSuffix}`;
    const totalPrice = `${currency.isoCode} 20.16${expectedSuffix}`;

    await ShopCustomer.expects(StorefrontProductDetail.productSinglePrice).toHaveText(productPrice);
    await ShopCustomer.attemptsTo(AddProductToCart(product));
    await ShopCustomer.expects(StorefrontProductDetail.offCanvasSummaryTotalPrice).toHaveText(totalPrice);
        
    await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());

    await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
    await ShopCustomer.attemptsTo(SelectInvoicePaymentOption());
    await ShopCustomer.attemptsTo(SelectStandardShippingOption());

    await ShopCustomer.expects(StorefrontCheckoutConfirm.taxPrice).not.toBeVisible();
    await ShopCustomer.expects(StorefrontCheckoutConfirm.grandTotalPrice).toHaveText(currency.isoCode+' 20.16');

    await ShopCustomer.attemptsTo(SubmitOrder());
    await ShopCustomer.expects(StorefrontCheckoutFinish.taxPrice).not.toBeVisible();
    await ShopCustomer.expects(StorefrontCheckoutFinish.grandTotalPrice).toHaveText(currency.isoCode+' 20.16');

    const orderId = StorefrontCheckoutFinish.getOrderId();

    TestDataService.addCreatedRecord('order', orderId);
});
