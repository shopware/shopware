import { test } from '@fixtures/AcceptanceTest';

test('As a merchant, I would be able to adjust storefront rounding for defined country', { tag: '@Settings' }, async ({
    ShopCustomer,
    TestDataService,
    DefaultSalesChannel,
    StorefrontProductDetail,
    StorefrontCheckoutConfirm,
    StorefrontCheckoutFinish,
    ChangeStorefrontCurrency,
    StorefrontHome,
    Login,
    ProceedFromProductToCheckout,
    AddProductToCart,
    ConfirmTermsAndConditions,
    SelectInvoicePaymentOption,
    SelectStandardShippingOption,
    SubmitOrder,
}) => {
    const product = await TestDataService.createBasicProduct();
    const currency = await TestDataService.createCurrency({ factor: 2.25555 });
    const country = await TestDataService.createCountry();
    const salutation = await TestDataService.getSalutation();
    const customer = await TestDataService.createCustomer({
        defaultShippingAddress: {
            firstName: 'John',
            lastName: 'Doe',
            city: 'Schöppingen',
            street: 'Ebbinghoff 10',
            zipcode: '48624',
            countryId: country.id,
            salutationId: salutation.id,
        },
        defaultBillingAddress: {
            firstName: 'John',
            lastName: 'Doe',
            city: 'Schöppingen',
            street: 'Ebbinghoff 10',
            zipcode: '48624',
            countryId: country.id,
            salutationId: salutation.id,
        },
    });
    await TestDataService.assignCurrencyCountryRounding(currency.id, country.id, 3);
    await TestDataService.assignSalesChannelCurrency(DefaultSalesChannel.salesChannel.id, currency.id);
    await TestDataService.assignSalesChannelCountry(DefaultSalesChannel.salesChannel.id, country.id);

    await ShopCustomer.attemptsTo(Login(customer));
    await ShopCustomer.goesTo(StorefrontHome.url());
    await ShopCustomer.attemptsTo(ChangeStorefrontCurrency(currency.isoCode));
    const productListingLocatorsByProductId = await StorefrontHome.getListingItemByProductId(product.id);
    await ShopCustomer.expects(productListingLocatorsByProductId.productPrice).toHaveText(currency.isoCode+' 22.556*');

    await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
    await ShopCustomer.expects(StorefrontProductDetail.productSinglePrice).toHaveText(currency.isoCode+' 22.556*');

    await ShopCustomer.attemptsTo(AddProductToCart(product));
    await ShopCustomer.expects(StorefrontProductDetail.offCanvasSummaryTotalPrice).toHaveText(currency.isoCode+' 22.556*');
    await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());

    await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
    await ShopCustomer.attemptsTo(SelectInvoicePaymentOption());
    await ShopCustomer.attemptsTo(SelectStandardShippingOption());

    await ShopCustomer.expects(StorefrontCheckoutConfirm.grandTotalPrice).toHaveText(currency.isoCode+' 22.556*');

    await ShopCustomer.attemptsTo(SubmitOrder());
    await ShopCustomer.expects(StorefrontCheckoutFinish.grandTotalPrice).toHaveText(currency.isoCode+' 22.556*');

    const orderId = StorefrontCheckoutFinish.getOrderId();

    TestDataService.addCreatedRecord('order', orderId);
});
