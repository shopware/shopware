import { test } from '@fixtures/AcceptanceTest';
import { satisfies } from 'compare-versions';

test('As a merchant, I would be able to adjust storefront rounding for defined country', { tag: ['@Settings', '@Storefront'] }, async ({
    ShopCustomer,
    TestDataService,
    DefaultSalesChannel,
    HomeProduct,
    StorefrontCheckoutConfirm,
    StorefrontCheckoutFinish,
    StorefrontHeader,
    StorefrontHome,
    StorefrontProductDetail,
    AddProductToCart,
    ChangeStorefrontCurrency,
    ConfirmTermsAndConditions,
    Login,
    ProceedFromProductToCheckout,
    SelectPaymentMethod,
    SelectShippingMethod,
    SubmitOrder,
    InstanceMeta,
}) => {
    const product = HomeProduct;
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
    
    // eslint-disable-next-line playwright/no-conditional-in-test
    if (satisfies(InstanceMeta.version, '<6.7') && !InstanceMeta.features['ACCESSIBILITY_TWEAKS']) {
        await StorefrontHeader.currenciesDropdown.click();
        await StorefrontHeader.currenciesMenuOptions.getByText(currency.symbol).click();
    }   
    else {
        await ShopCustomer.attemptsTo(ChangeStorefrontCurrency(currency.name));
    }

    const productListingLocatorsByProductId = await StorefrontHome.getListingItemByProductName(product.name);
    await ShopCustomer.expects(productListingLocatorsByProductId.productPrice).toContainText(currency.isoCode + ' 22.556');

    await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
    await ShopCustomer.expects(StorefrontProductDetail.productSinglePrice).toContainText(currency.isoCode + ' 22.556');

    await ShopCustomer.attemptsTo(AddProductToCart(product));
    await ShopCustomer.expects(StorefrontProductDetail.offCanvasSummaryTotalPrice).toContainText(currency.isoCode + ' 22.556');
    await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());

    await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
    await ShopCustomer.attemptsTo(SelectPaymentMethod('Invoice'));
    await ShopCustomer.attemptsTo(SelectShippingMethod('Standard'));

    await ShopCustomer.expects(StorefrontCheckoutConfirm.grandTotalPrice).toContainText(currency.isoCode + ' 22.556');

    await ShopCustomer.attemptsTo(SubmitOrder());
    await ShopCustomer.expects(StorefrontCheckoutFinish.grandTotalPrice).toContainText(currency.isoCode + ' 22.556');

    const orderId = StorefrontCheckoutFinish.getOrderId();

    TestDataService.addCreatedRecord('order', orderId);
});
