import { test } from '@fixtures/AcceptanceTest';

test('As an admin, I can create and verify customer groups in the admin.', { tag: '@CustomerGroups' }, async ({
    TestDataService,
    ShopAdmin,
    AdminCustomerGroupListing,
    AdminCustomerGroupDetail,
    DefaultSalesChannel,
    InstanceMeta,
}) => {

    test.skip(InstanceMeta.features['V6_7_0_0'], 'This test is incompatible with V6_7_0_0, ticket: https://shopware.atlassian.net/browse/NEXT-40162');

    const customerGroup = await TestDataService.createCustomerGroup();

    await test.step('Verify the created customer group in the admin', async () => {
        await ShopAdmin.goesTo(AdminCustomerGroupListing.url());
        const customerGroupLineItem = await AdminCustomerGroupListing.getCustomerGroupByName(customerGroup.name);
        await ShopAdmin.expects(customerGroupLineItem.customerGroupName).toBeVisible({ timeout: 10000 });
        await ShopAdmin.goesTo(AdminCustomerGroupDetail.url(customerGroup.id));
        await ShopAdmin.expects(AdminCustomerGroupDetail.headline).toContainText(customerGroup.name);
        await ShopAdmin.expects(AdminCustomerGroupDetail.customerGroupNameField).toHaveValue(customerGroup.name);
        await ShopAdmin.expects(AdminCustomerGroupDetail.customerGroupGrossTaxDisplay).toBeChecked();
        await ShopAdmin.expects(AdminCustomerGroupDetail.customSignupFormToggle).toBeChecked();
        await ShopAdmin.expects(AdminCustomerGroupDetail.signupFormTitle).toHaveValue(customerGroup.name);
        await ShopAdmin.expects(AdminCustomerGroupDetail.signupFormIntroduction).toContainText(`${customerGroup.name}-Introduction`);
        await ShopAdmin.expects(AdminCustomerGroupDetail.signupFormSeoDescription).toHaveValue(`${customerGroup.name}-SEO-Description`);
        await ShopAdmin.expects(AdminCustomerGroupDetail.signupFormCompanySignupToggle).not.toBeChecked();
        await ShopAdmin.expects(AdminCustomerGroupDetail.selectedSalesChannel).toContainText(DefaultSalesChannel.salesChannel.name);
        await ShopAdmin.expects(AdminCustomerGroupDetail.technicalUrl).toHaveValue(new RegExp(`${customerGroup.id}`));
        await ShopAdmin.expects(AdminCustomerGroupDetail.saleschannelUrl).toHaveValue(new RegExp(`${customerGroup.name}`));
    });

});
test('As a customer, I must be able to register under a customer group in the Storefront.', { tag: '@Registration @CustomerGroups' }, async ({
    TestDataService,
    ShopAdmin,
    ShopCustomer,
    StorefrontAccount,
    StorefrontCustomRegister,
    IdProvider,
    Register,
    CustomerGroupActivation,
    InstanceMeta,
}) => {

    test.skip(InstanceMeta.features['V6_7_0_0'], 'This test is incompatible with V6_7_0_0, ticket: https://shopware.atlassian.net/browse/NEXT-40163');

    const customer = { email: IdProvider.getIdPair().uuid + '@test.com' };
    const customerGroup = await TestDataService.createCustomerGroup();

    await test.step('Register the customer and activate it for the customer group', async () => {
        await ShopCustomer.goesTo(StorefrontCustomRegister.url(customerGroup.name));
        await ShopCustomer.attemptsTo(Register(customer));
        await ShopCustomer.expects(StorefrontAccount.page.getByText(customer.email, { exact: true })).toBeVisible();
        const customerGroupAlert = await StorefrontAccount.getCustomerGroupAlert(customerGroup.name);
        await ShopCustomer.expects(customerGroupAlert).toContainText(customerGroup.name);
        await ShopAdmin.attemptsTo(CustomerGroupActivation(customer.email, customerGroup.name));
    });

    await test.step('Verify that the customer group request message is not displayed on the Storefront', async () => {
        await ShopCustomer.goesTo(StorefrontAccount.url());
        await ShopCustomer.expects(StorefrontAccount.page.getByText(customer.email, { exact: true })).toBeVisible();
        await ShopCustomer.expects(StorefrontAccount.customerGroupRequestMessage).not.toBeVisible();
    });

});

test('As a commercial customer, I must be able to register under a customer group in the Storefront.', { tag: '@Registration @CustomerGroups' }, async ({
    TestDataService,
    ShopAdmin,
    ShopCustomer,
    StorefrontAccount,
    StorefrontCustomRegister,
    IdProvider,
    Register,
    CustomerGroupActivation,
    InstanceMeta,
}) => {

    test.skip(InstanceMeta.features['V6_7_0_0'], 'This test is incompatible with V6_7_0_0, ticket: https://shopware.atlassian.net/browse/NEXT-40163');

    const uuid = IdProvider.getIdPair().uuid;
    const customer = { email: uuid + '@test.com', vatRegNo: uuid + '-VatId'};
    const commercialCustomerGroup = await TestDataService.createCustomerGroup({ registrationOnlyCompanyRegistration: true });

    await test.step('Register the commercial customer and activate it for the customer group', async () => {
        await ShopCustomer.goesTo(StorefrontCustomRegister.url(commercialCustomerGroup.name));
        await ShopCustomer.attemptsTo(Register(customer, true));
        await ShopCustomer.expects(StorefrontAccount.page.getByText(customer.email, { exact: true })).toBeVisible();
        const customerGroupAlert = await StorefrontAccount.getCustomerGroupAlert(commercialCustomerGroup.name);
        await ShopCustomer.expects(customerGroupAlert).toContainText(commercialCustomerGroup.name);
        await ShopAdmin.attemptsTo(CustomerGroupActivation(customer.email, commercialCustomerGroup.name));
    });

    await test.step('Verify that the customer group request message is not displayed on the Storefront', async () => {
        await ShopCustomer.goesTo(StorefrontAccount.url());
        await ShopCustomer.expects(StorefrontAccount.page.getByText(customer.email, { exact: true })).toBeVisible();
        await ShopCustomer.expects(StorefrontAccount.page.getByText(customer.vatRegNo)).toBeVisible();
        await ShopCustomer.expects(StorefrontAccount.customerGroupRequestMessage).not.toBeVisible();
    });

});
