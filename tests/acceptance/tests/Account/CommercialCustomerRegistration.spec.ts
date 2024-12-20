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

    //eslint-disable-next-line
    if (InstanceMeta.isSaaS) {
        // eslint-disable-next-line playwright/no-skipped-test
        test.skip();
    }
    //eslint-disable-next-line
    if (InstanceMeta.features['V6_7_0_0']) {
        // eslint-disable-next-line playwright/no-skipped-test
        test.skip(true, 'This test has a bug: https://shopware.atlassian.net/browse/NEXT-40118');
    }

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
