import { test } from '@fixtures/AcceptanceTest';

test ('New Customers must be able to register in the Storefront', async ({
    shopCustomer,
    accountLoginPage,
    Register,
}) => {

    await shopCustomer.goesTo(accountLoginPage);
    await shopCustomer.attemptsTo(Register());
})