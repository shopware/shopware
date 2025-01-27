import { test, expect } from '@fixtures/AcceptanceTest';

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
    const customer = { isCommercial: true, email: uuid + '@test.com', vatRegNo: uuid + '-VatId' };
    await TestDataService.setSystemConfig({ 'core.loginRegistration.showAccountTypeSelection': true });

    await ShopCustomer.goesTo(StorefrontAccountLogin.url());
    await StorefrontAccountLogin.accountTypeSelect.selectOption('Commercial');
    await ShopCustomer.attemptsTo(Register(customer));
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
    const customer = { isCommercial: true , country: country.name, vatRegNo: '' };

    await ShopCustomer.goesTo(StorefrontAccountLogin.url());
    await StorefrontAccountLogin.accountTypeSelect.selectOption('Commercial');
    await ShopCustomer.attemptsTo(Register(customer));
    await ShopCustomer.expects(StorefrontAccountLogin.vatRegNoInput).toHaveCSS('border-color', 'rgb(194, 0, 23)');

    if (InstanceMeta.features['ACCESSIBILITY_TWEAKS']) {
        await ShopCustomer.expects(StorefrontAccountLogin.vatRegNoInput).toHaveAttribute('aria-required');
    } else {
        await ShopCustomer.expects(StorefrontAccountLogin.vatRegNoInput).toHaveAttribute('required');
    }

    await ShopCustomer.expects(StorefrontAccountLogin.page.getByText('I\'m a new customer!')).toBeVisible();
});

test('As a new customer, I should not be able to register as a commercial customer without providing a valid VAT Reg.No.', { tag: '@Registration' }, async ({
    ShopCustomer,
    StorefrontAccountLogin,
    IdProvider,
    Register,
    TestDataService,
    InstanceMeta,
    AdminApiContext,
}) => {
    test.skip(InstanceMeta.isSaaS, 'This test is incompatible with SaaS');
    test.skip(InstanceMeta.features['V6_7_0_0'], 'This test has a bug: https://shopware.atlassian.net/browse/NEXT-40297');
    // TODO: Use a new country created by the TestDataService after this bug is fixed: https://shopware.atlassian.net/browse/NEXT-40285

    await TestDataService.setSystemConfig({ 'core.loginRegistration.showAccountTypeSelection': true });
    const country = await TestDataService.getCountryId('DE');
    const uuid = IdProvider.getIdPair().uuid;
    const customer = { isCommercial: true, vatRegNo: `${uuid}-VatId` };

    const newSettings = await AdminApiContext.patch(`./country/${country.id}`, {
        data: { checkVatIdPattern: true },
    });
    expect(newSettings.ok()).toBeTruthy();

    try {
        await ShopCustomer.goesTo(StorefrontAccountLogin.url());

        // Attempt to register the customer with the invalid VAT ID.
        await StorefrontAccountLogin.accountTypeSelect.selectOption('Commercial');
        await ShopCustomer.attemptsTo(Register(customer));

        await ShopCustomer.expects(StorefrontAccountLogin.vatRegNoInput).toHaveCSS('border-color', 'rgb(194, 0, 23)');
        await ShopCustomer.expects(StorefrontAccountLogin.page.locator('.invalid-feedback')).toContainText('The VAT Reg.No. you have entered does not have the correct format.');
        await ShopCustomer.expects(StorefrontAccountLogin.page.getByText('I\'m a new customer!')).toBeVisible();
    } finally {
        const revertSettings = await AdminApiContext.patch(`./country/${country.id}`, {
            data: { checkVatIdPattern: false },
        });
        expect(revertSettings.ok()).toBeTruthy();
    }
});
