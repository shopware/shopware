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

test('As a new customer, I cannot register as a commercial customer without providing a VAT Reg.No.', { tag: '@Registration' }, async ({
    ShopCustomer,
    StorefrontAccountLogin,
    Register,
    TestDataService,
    DefaultSalesChannel,
    InstanceMeta,
}) => {
    test.skip(InstanceMeta.isSaaS, 'This test is incompatible with SaaS');

    await TestDataService.setSystemConfig({ 'core.loginRegistration.showAccountTypeSelection': true });
    const country = await TestDataService.createCountry({ vatIdRequired: true });
    await TestDataService.assignSalesChannelCountry(DefaultSalesChannel.salesChannel.id, country.id);
    const customer = { country: country.name, vatRegNo: '' };

    await ShopCustomer.goesTo(StorefrontAccountLogin.url());
    await StorefrontAccountLogin.accountTypeSelect.selectOption('Commercial');
    await ShopCustomer.attemptsTo(Register(customer, true));
    await ShopCustomer.expects(StorefrontAccountLogin.vatRegNoInput).toHaveCSS('border-color', 'rgb(194, 0, 23)');
    await ShopCustomer.expects(StorefrontAccountLogin.page.locator('label[for="vatIds"]')).toContainText('VAT Reg.No. *')
    await ShopCustomer.expects(StorefrontAccountLogin.page.getByText('I\'m a new customer!')).toBeVisible();
});
