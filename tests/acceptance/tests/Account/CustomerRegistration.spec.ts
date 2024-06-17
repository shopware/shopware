import { test } from '@fixtures/AcceptanceTest';

test ('New Customers must be able to register in the Storefront', async ({
    ShopCustomer,
    StorefrontAccountLogin,
    StorefrontAccount,
    IdProvider,
    Register,
}) => {
    const email = IdProvider.getIdPair().uuid + '@test.com';

    await ShopCustomer.goesTo(StorefrontAccountLogin.url());
    await ShopCustomer.attemptsTo(Register(email));
    await ShopCustomer.expects(StorefrontAccount.page.getByText(email, { exact: true })).toBeVisible();
});
