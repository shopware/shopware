import { test } from '@fixtures/AcceptanceTest';

test('As a new customer, I must be able to register in the Storefront.', { tag: '@Registration' }, async ({
    ShopCustomer,
    StorefrontAccountLogin,
    StorefrontAccount,
    IdProvider,
    Register,
    InstanceMeta,
}) => {


    if (InstanceMeta.features['V6_7_0_0']) {
         test.skip(true, 'This test is incompatible with V6_7_0_0');
    }

    const customer = { email: IdProvider.getIdPair().uuid + '@test.com' };

    await ShopCustomer.goesTo(StorefrontAccountLogin.url());
    await ShopCustomer.attemptsTo(Register(customer));
    await ShopCustomer.expects(StorefrontAccount.page.getByText(customer.email, { exact: true })).toBeVisible();
});
