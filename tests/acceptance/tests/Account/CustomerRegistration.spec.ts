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
