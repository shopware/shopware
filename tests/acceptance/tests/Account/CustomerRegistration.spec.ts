import { test } from '@fixtures/AcceptanceTest';

test('As a new customer, I must be able to register in the Storefront.', { tag: '@Registration' }, async ({
    ShopCustomer,
    StorefrontAccountLogin,
    StorefrontAccount,
    IdProvider,
    Register,
}) => {
    const customer = { email: IdProvider.getIdPair().uuid + '@test.com' };

    await ShopCustomer.goesTo(StorefrontAccountLogin.url());
    await ShopCustomer.attemptsTo(Register(customer));
    await ShopCustomer.expects(StorefrontAccount.page.getByText(customer.email, { exact: true })).toBeVisible();
});

test('As a new customer, I should not be able to register with empty postal code when it is required', { tag: '@Registration' }, async ({
    ShopCustomer,
    StorefrontAccountLogin,
    Register,
    TestDataService,
    DefaultSalesChannel,
}) => {
    const country = await TestDataService.createCountry({ postalCodeRequired: true });
    await TestDataService.assignSalesChannelCountry(DefaultSalesChannel.salesChannel.id, country.id);
    const customer = { country: country.name, postalCode: '' };
    await ShopCustomer.goesTo(StorefrontAccountLogin.url());
    await ShopCustomer.attemptsTo(Register(customer));
    await ShopCustomer.expects(StorefrontAccountLogin.postalCodeInput).toHaveCSS('border-color', 'rgb(194, 0, 23)');
    await ShopCustomer.expects(StorefrontAccountLogin.page.getByText('I\'m a new customer!')).toBeVisible();
});
