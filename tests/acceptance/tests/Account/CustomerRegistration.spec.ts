import { test } from '@fixtures/AcceptanceTest';

test ('New Customers must be able to register in the Storefront', async ({
    shopCustomer,
    accountLoginPage,
    accountPage,
    idProvider,
    Register,
}) => {

    const email = idProvider.getIdPair().uuid + '@test.com';

    await shopCustomer.goesTo(accountLoginPage);
    await shopCustomer.attemptsTo(Register(email));
    await shopCustomer.expects(accountPage.page.getByText(email, { exact: true })).toBeVisible();

});