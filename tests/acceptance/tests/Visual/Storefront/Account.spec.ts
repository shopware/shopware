import { test } from '@fixtures/AcceptanceTest';

test('Visual: Storefront Account Pages', { tag: '@Visual' }, async ({
    ShopCustomer,
    DefaultSalesChannel,
    TestDataService,
    StorefrontAccountLogin,
    StorefrontAccount,
    StorefrontAccountProfile,
    StorefrontAccountAddresses,
    StorefrontAccountOrder,
    Login,
    
}) => {
    const countryId = await DefaultSalesChannel.salesChannel.countryId;
    const salutationId = await DefaultSalesChannel.salesChannel.salutationId;
    const customer = await TestDataService.createCustomer({
        firstName: 'John',
        lastName: 'Goldblum',
        customerNumber: '12345',
        email: 'johngoldblum@example.com',
        password: 'shopware',
        createdAt: '2025-09-04T06:36:38.101+00:00',
        defaultShippingAddress: {
            firstName: 'John',
            lastName: 'Doe',
            city: 'Schöppingen',
            street: 'Ebbinghoff 10',
            zipcode: '48624',
            countryId: countryId,
            salutationId: salutationId,
        },
        defaultBillingAddress: {
            firstName: 'John',
            lastName: 'Doe',
            city: 'Schöppingen',
            street: 'Ebbinghoff 10',
            zipcode: '48624',
            countryId: countryId,
            salutationId: salutationId,
        },
    });

    await TestDataService.setSystemConfig({ 'core.basicInformation.useDefaultCookieConsent': false });

    await test.step('Create screenshot of account login page in storefront.', async () => {
        await ShopCustomer.goesTo(StorefrontAccountLogin.url());
        await ShopCustomer.expects(StorefrontAccountLogin.page).toHaveScreenshot('Account-Login-Page.png', {
            fullPage: true,
        });
    });

    await test.step('Create screenshot of account overview page in storefront.', async () => {
        await ShopCustomer.attemptsTo(Login(customer));
        await ShopCustomer.expects(StorefrontAccount.navigation.overviewLink).toBeVisible();
        await ShopCustomer.expects(StorefrontAccount.page).toHaveScreenshot('Account-Overview-Page.png', {
            fullPage: true,
        });
    });

    await test.step('Create screenshot of account profile page in storefront.', async () => {
        await StorefrontAccount.navigation.yourProfileLink.click();
        await ShopCustomer.expects(StorefrontAccountProfile.changePasswordButton).toBeVisible();
        await ShopCustomer.expects(StorefrontAccountProfile.page).toHaveScreenshot('Account-Profile-Page.png', {
            fullPage: true,
        });
    });

    await test.step('Create screenshot of account addresses page in storefront.', async () => {
        await StorefrontAccountProfile.navigation.addressesLink.click();
        await ShopCustomer.expects(StorefrontAccountAddresses.addNewAddressButton).toBeVisible();
        await ShopCustomer.expects(StorefrontAccountAddresses.page).toHaveScreenshot('Account-Addresses-Page.png', {
            fullPage: true,
        });
    });

    await test.step('Create screenshot of account order page in storefront.', async () => {
        await StorefrontAccountAddresses.navigation.ordersLink.click();
        await ShopCustomer.expects(StorefrontAccountOrder.noOrdersAlert).toBeVisible();
        await ShopCustomer.expects(StorefrontAccountOrder.page).toHaveScreenshot('Account-Order-Page.png', {
            fullPage: true,
        });
    });
});
