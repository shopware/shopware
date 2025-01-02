import { test } from '@fixtures/AcceptanceTest';

test('As a new customer, I must be able to register as a commercial customer in the Storefront.', { tag: '@Registration' }, async ({
    ShopCustomer,
    StorefrontAccountLogin,
    StorefrontAccount,
    IdProvider,
    Register,
    TestDataService,
    InstanceMeta,
}) => {
    test.skip(InstanceMeta.isSaaS, 'This test is incompatible with SaaS');
    test.skip(InstanceMeta.features['V6_7_0_0'], 'This test has a bug: https://shopware.atlassian.net/browse/NEXT-40118');

    const uuid = IdProvider.getIdPair().uuid;
    const customer = { email: uuid + '@test.com', vatRegNo: uuid + '-VatId' };
    await TestDataService.setSystemConfig({ 'core.loginRegistration.showAccountTypeSelection': true });

    await ShopCustomer.goesTo(StorefrontAccountLogin.url());
    await StorefrontAccountLogin.accountTypeSelect.selectOption('Commercial');
    await ShopCustomer.attemptsTo(Register(customer, true));
    await ShopCustomer.expects(StorefrontAccount.page.getByText(customer.email, { exact: true })).toBeVisible();
    await ShopCustomer.expects(StorefrontAccount.page.getByText('shopware - Operations VAT Reg')).toBeVisible();
    await ShopCustomer.expects(StorefrontAccount.page.getByText('shopware - Operations VAT Reg')).toContainText(customer.vatRegNo);

});
