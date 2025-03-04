import { test } from '@fixtures/AcceptanceTest';

test('New customers can register as commercial customers in the Storefront.', { tag: '@Registration' }, async ({
    ShopCustomer,
    StorefrontAccountLogin,
    StorefrontAccount,
    IdProvider,
    Register,
    TestDataService,
    InstanceMeta,
}) => {
    test.skip(InstanceMeta.isSaaS, 'This test is incompatible with SaaS');

    const uuid = IdProvider.getIdPair().uuid;
    const customer = { isCommercial: true, email: uuid + '@test.com', vatRegNo: uuid + '-VatId' };
    await TestDataService.setSystemConfig({ 'core.loginRegistration.showAccountTypeSelection': true });

    await test.step('Register as a commercial customer', async () => {
        await ShopCustomer.goesTo(StorefrontAccountLogin.url());
        await StorefrontAccountLogin.accountTypeSelect.selectOption('Commercial');
        await ShopCustomer.attemptsTo(Register(customer));
    });

    await test.step('Verify successful commercial customer registration', async () => {
        await ShopCustomer.expects(StorefrontAccount.page.getByText(customer.email, { exact: true })).toBeVisible();
        await ShopCustomer.expects(StorefrontAccount.page.getByText('shopware - Operations VAT Reg')).toContainText(customer.vatRegNo);
    });

});

test('New customers cannot register as commercial customers without a VAT Reg.No.', { tag: '@Registration' }, async ({
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
    const customer = { isCommercial: true, country: country.name, vatRegNo: '' };

    await test.step('Attempt to register without a VAT Reg.No.', async () => {
        await ShopCustomer.goesTo(StorefrontAccountLogin.url());
        await StorefrontAccountLogin.accountTypeSelect.selectOption('Commercial');
        await ShopCustomer.attemptsTo(Register(customer));
    });

    await test.step('Verify registration is blocked due to missing VAT Reg.No.', async () => {
        await ShopCustomer.expects(StorefrontAccountLogin.page.getByText('I\'m a new customer!')).toBeVisible();
        await ShopCustomer.expects(StorefrontAccountLogin.vatRegNoInput).toHaveAttribute('aria-required');
        await ShopCustomer.expects(StorefrontAccountLogin.vatRegNoInput).toHaveCSS('border-color', 'rgb(194, 0, 23)');
    });

});

test('New customers cannot register as commercial customers with an invalid VAT Reg.No.', { tag: '@Registration' }, async ({
    ShopCustomer,
    StorefrontAccountLogin,
    IdProvider,
    Register,
    TestDataService,
    InstanceMeta,
    DefaultSalesChannel,
}) => {
    test.skip(InstanceMeta.isSaaS, 'This test is incompatible with SaaS');

    await TestDataService.setSystemConfig({ 'core.loginRegistration.showAccountTypeSelection': true });
    const country = await TestDataService.createCountry({ checkVatIdPattern: true, vatIdPattern: 'DE\\d{9}' });
    await TestDataService.assignSalesChannelCountry(DefaultSalesChannel.salesChannel.id, country.id);
    const uuid = IdProvider.getIdPair().uuid;
    const customer = { isCommercial: true, vatRegNo: `${uuid}-VatId`, country: country.name };

    await test.step('Attempt to register with an invalid VAT Reg.No.', async () => {
        await ShopCustomer.goesTo(StorefrontAccountLogin.url());
        await StorefrontAccountLogin.accountTypeSelect.selectOption('Commercial');
        await ShopCustomer.attemptsTo(Register(customer));
    });

    await test.step('Verify registration is blocked due to invalid VAT Reg.No. format', async () => {
        await ShopCustomer.expects(StorefrontAccountLogin.page.getByText('I\'m a new customer!')).toBeVisible();
        await ShopCustomer.expects(StorefrontAccountLogin.vatRegNoInput).toHaveCSS('border-color', 'rgb(194, 0, 23)');
        await ShopCustomer.expects(StorefrontAccountLogin.page.locator('.invalid-feedback')).toContainText('The VAT Reg.No. you have entered does not have the correct format.');
    });

});
