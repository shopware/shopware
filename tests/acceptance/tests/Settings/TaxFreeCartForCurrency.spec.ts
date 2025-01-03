import { test } from '@fixtures/AcceptanceTest';

test('As a merchant, I would be able to adjust free tax for defined currency', { tag: '@Settings' }, async ({
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
    InstanceMeta,
}) => {

    test.skip(InstanceMeta.features['V6_7_0_0'], 'This test is incompatible with V6_7_0_0 Ticket already created: https://shopware.atlassian.net/browse/NEXT-40119');

    const product = await TestDataService.createBasicProduct();
    const currency = await TestDataService.createCurrency({ taxFreeFrom: 5 });
    const customer = await TestDataService.createCustomer();
    await TestDataService.assignSalesChannelCurrency(DefaultSalesChannel.salesChannel.id, currency.id);

    await ShopCustomer.attemptsTo(Login(customer));

    await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
    await ShopCustomer.attemptsTo(ChangeStorefrontCurrency(currency.isoCode));
    await ShopCustomer.expects(StorefrontProductDetail.productSinglePrice).toHaveText(currency.isoCode+' 24.00*');

    await ShopCustomer.attemptsTo(AddProductToCart(product));
    await ShopCustomer.expects(StorefrontProductDetail.offCanvasSummaryTotalPrice).toHaveText(currency.isoCode+' 20.16*');

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
