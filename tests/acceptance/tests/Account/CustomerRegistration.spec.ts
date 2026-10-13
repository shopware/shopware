import { test } from '@fixtures/AcceptanceTest';
import { satisfies } from 'compare-versions';

test('As a new customer, I must be able to register in the Storefront.', { tag: ['@Registration', '@Storefront'] }, async ({
    ShopCustomer,
    StorefrontAccountLogin,
    StorefrontAccount,
    IdProvider,
    Register,
}) => {
    const customer = { email: IdProvider.getIdPair().uuid + '@test.com' };

    await ShopCustomer.goesTo(StorefrontAccountLogin.url());
    await ShopCustomer.attemptsTo(Register(customer));
    await ShopCustomer.expects(StorefrontAccount.page.getByText(customer.email, { exact: true })).toBeVisible();
});

test('As a new customer, the registration privacy notice matches the checkbox configuration.', { tag: ['@Registration', '@Storefront'] }, async ({
    ShopCustomer,
    StorefrontAccountLogin,
    TestDataService,
}) => {
    const privacyNotice = StorefrontAccountLogin.page.locator('.register-form .privacy-notice');
    const dataProtectionCheckbox = privacyNotice.locator('input[name="acceptedDataProtection"]');

    await TestDataService.setSystemConfig({ 'core.loginRegistration.requireDataProtectionCheckbox': false });
    await ShopCustomer.goesTo(`${StorefrontAccountLogin.url()}?privacy-checkbox=disabled`);

    await ShopCustomer.expects(dataProtectionCheckbox).toHaveCount(0);
    await ShopCustomer.expects(privacyNotice.locator('.data-protection-information')).toContainText(/Please note|Bitte beachten/i);
    await ShopCustomer.expects(StorefrontAccountLogin.registerButton).toContainText(/Register|Registrieren/i);

    await TestDataService.setSystemConfig({ 'core.loginRegistration.requireDataProtectionCheckbox': true });
    await ShopCustomer.goesTo(`${StorefrontAccountLogin.url()}?privacy-checkbox=enabled`);

    await ShopCustomer.expects(dataProtectionCheckbox).toBeVisible();
    await ShopCustomer.expects(privacyNotice.locator('label[for="acceptedDataProtection"]')).not.toContainText(/Please note|Bitte beachten/i);
});

test('As a new customer, I should not be able to register with empty postal code when it is required.', { tag: ['@Registration', '@Storefront'] }, async ({
    ShopCustomer,
    StorefrontAccountLogin,
    Register,
    TestDataService,
    DefaultSalesChannel,
    InstanceMeta,
}) => {
    const country = await TestDataService.createCountry({ postalCodeRequired: true });
    await TestDataService.assignSalesChannelCountry(DefaultSalesChannel.salesChannel.id, country.id);
    const customer = { country: country.name, postalCode: '' };

    await test.step('Attempt to register with missing postal code', async () => {
        await ShopCustomer.goesTo(StorefrontAccountLogin.url());
        await ShopCustomer.attemptsTo(Register(customer));
    });

    await test.step('Validate registration is blocked due to missing postal code', async () => {
        await ShopCustomer.expects(StorefrontAccountLogin.page.getByText('I\'m a new customer!')).toBeVisible();
        await ShopCustomer.expects(StorefrontAccountLogin.page.locator('label[for="billingAddressAddressZipcode"]')).toContainText('*');
        await ShopCustomer.expects(StorefrontAccountLogin.postalCodeInput).toHaveCSS('border-color', 'rgb(194, 0, 23)');

        // eslint-disable-next-line playwright/no-conditional-in-test
        if (!satisfies(InstanceMeta.version, '<6.7')) {
            await ShopCustomer.expects(StorefrontAccountLogin.page.locator('.invalid-feedback')).toContainText('Input should not be empty.');
        }
    });
});