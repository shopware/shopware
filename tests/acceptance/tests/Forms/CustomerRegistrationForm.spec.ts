import { test, getLocale } from '@fixtures/AcceptanceTest';
import { getAddressDataFromLocale } from '../../helpers/locale-helpers';

test.describe('Customer Registration Form', () => {
    test.beforeEach(async ({ TestDataService, InstanceMeta }) => {
        test.skip(InstanceMeta.isSaaS, 'SaaS just support FriendlyCaptcha');

        await TestDataService.setSystemConfig({
            'core.basicInformation.activeCaptchasV2': {
                googleReCaptchaV2: {
                    name: 'googleReCaptchaV2',
                    isActive: false,
                    config: {
                        siteKey: '',
                        secretKey: '',
                        invisible: false,
                    },
                },
            },
        });
    });

    test(
        'As a customer, the registration privacy notice matches the checkbox and legal guarantee configuration.',
        {
            tag: [
                '@Form',
                '@Registration',
                '@Storefront',
            ],
        },
        async ({ ShopCustomer, StorefrontAccountLogin, TestDataService }) => {
            const privacyNotice = StorefrontAccountLogin.page.locator('.register-form .privacy-notice');
            const dataProtectionCheckbox = privacyNotice.locator('input[name="acceptedDataProtection"]');
            const privacyLabel = privacyNotice.locator('label[for="acceptedDataProtection"], .data-protection-information');
            const legalGuaranteeNoticeParagraph = privacyNotice.locator('.legal-guarantee-notice');
            const legalGuaranteeNoticeLink = privacyNotice.locator('[data-bs-target="#legalGuaranteeNoticeModal"]');
            const legalGuaranteeNoticeModal = StorefrontAccountLogin.page.locator('#legalGuaranteeNoticeModal');

            // Checkbox disabled, legal guarantee notice disabled: only the passive privacy notice is shown.
            await TestDataService.setSystemConfig({
                'core.loginRegistration.requireDataProtectionCheckbox': false,
                'core.cart.showLegalGuaranteeNotice': false,
            });
            await ShopCustomer.goesTo(`${StorefrontAccountLogin.url()}?privacy-checkbox=disabled&guarantee=disabled`);

            await ShopCustomer.expects(dataProtectionCheckbox).toHaveCount(0);
            await ShopCustomer.expects(privacyLabel).toContainText(/Please note|Bitte beachten/i);
            await ShopCustomer.expects(legalGuaranteeNoticeParagraph).toHaveCount(0);
            await ShopCustomer.expects(legalGuaranteeNoticeLink).toHaveCount(0);
            await ShopCustomer.expects(StorefrontAccountLogin.registerButton).toContainText(/Register|Registrieren/i);

            // Checkbox disabled, legal guarantee notice enabled: the notice is shown as its own paragraph.
            await TestDataService.setSystemConfig({
                'core.cart.showLegalGuaranteeNotice': true,
            });
            await ShopCustomer.goesTo(`${StorefrontAccountLogin.url()}?privacy-checkbox=disabled&guarantee=enabled`);

            await ShopCustomer.expects(dataProtectionCheckbox).toHaveCount(0);
            await ShopCustomer.expects(legalGuaranteeNoticeParagraph).toBeVisible();
            await ShopCustomer.expects(legalGuaranteeNoticeLink).toBeVisible();

            await legalGuaranteeNoticeLink.click();

            await ShopCustomer.expects(legalGuaranteeNoticeModal).toBeVisible();
            await ShopCustomer.expects(legalGuaranteeNoticeModal).toContainText(
                /Your legal guarantee rights|Ihre gesetzlichen Gewährleistungsrechte/i,
            );
            await ShopCustomer.expects(legalGuaranteeNoticeModal.getByRole('link')).toBeVisible();

            // Checkbox enabled, legal guarantee notice enabled: both the checkbox and the notice paragraph are shown.
            await TestDataService.setSystemConfig({
                'core.loginRegistration.requireDataProtectionCheckbox': true,
            });
            await ShopCustomer.goesTo(`${StorefrontAccountLogin.url()}?privacy-checkbox=enabled&guarantee=enabled`);

            await ShopCustomer.expects(dataProtectionCheckbox).toBeVisible();
            await ShopCustomer.expects(privacyLabel).not.toContainText(/Please note|Bitte beachten/i);
            await ShopCustomer.expects(legalGuaranteeNoticeParagraph).toBeVisible();
            await ShopCustomer.expects(legalGuaranteeNoticeLink).toBeVisible();

            // Checkbox enabled, legal guarantee notice disabled: the checkbox stays, the notice paragraph disappears.
            await TestDataService.setSystemConfig({
                'core.cart.showLegalGuaranteeNotice': false,
            });
            await ShopCustomer.goesTo(`${StorefrontAccountLogin.url()}?privacy-checkbox=enabled&guarantee=disabled`);

            await ShopCustomer.expects(dataProtectionCheckbox).toBeVisible();
            await ShopCustomer.expects(legalGuaranteeNoticeParagraph).toHaveCount(0);
            await ShopCustomer.expects(legalGuaranteeNoticeLink).toHaveCount(0);
        },
    );

    test(
        'As a customer, I can perform a registration without captcha protection.',
        {
            tag: [
                '@Form',
                '@Registration',
                '@Storefront',
            ],
        },
        async ({ ShopCustomer, StorefrontAccountLogin, StorefrontAccount, IdProvider, Register }) => {
            const customer = { email: `${IdProvider.getIdPair().uuid}@test.com` };

            await ShopCustomer.goesTo(StorefrontAccountLogin.url());
            await ShopCustomer.attemptsTo(Register(customer));

            await StorefrontAccountLogin.page.waitForURL('**/account', { waitUntil: 'commit' });

            await ShopCustomer.expects(StorefrontAccount.page.getByText(customer.email, { exact: true })).toBeVisible();
        },
    );

    test(
        'As a customer, I can perform a registration with full customer data without captcha protection.',
        {
            tag: [
                '@Form',
                '@Registration',
                '@Storefront',
            ],
        },
        async ({ ShopCustomer, StorefrontAccountLogin, StorefrontAccount, IdProvider }) => {
            const locale = getLocale();
            const addressData = getAddressDataFromLocale(locale);

            const customer = {
                salutation: 'Mr.',
                firstName: 'Jeff',
                lastName: 'Goldblum',
                email: `${IdProvider.getIdPair().uuid}@test.com`,
                password: 'shopware',
                street: addressData.street,
                city: addressData.city,
                country: addressData.country,
                postalCode: addressData.postalCode,
            };

            await ShopCustomer.goesTo(StorefrontAccountLogin.url());

            await StorefrontAccountLogin.salutationSelect.selectOption(customer.salutation);
            await StorefrontAccountLogin.firstNameInput.fill(customer.firstName);
            await StorefrontAccountLogin.lastNameInput.fill(customer.lastName);
            await StorefrontAccountLogin.registerEmailInput.fill(customer.email);
            await StorefrontAccountLogin.registerPasswordInput.fill(customer.password);

            await StorefrontAccountLogin.streetAddressInput.fill(customer.street);
            await StorefrontAccountLogin.postalCodeInput.fill(customer.postalCode);
            await StorefrontAccountLogin.cityInput.fill(customer.city);
            await StorefrontAccountLogin.countryInput.selectOption({ label: customer.country });

            await StorefrontAccountLogin.registerButton.click();

            await StorefrontAccountLogin.page.waitForURL('**/account', { waitUntil: 'commit' });

            await ShopCustomer.expects(StorefrontAccount.page.getByText(customer.email, { exact: true })).toBeVisible();
        },
    );

    test(
        'As a customer, I can perform a registration with validation errors without captcha protection.',
        {
            tag: [
                '@Form',
                '@Registration',
                '@Storefront',
            ],
        },
        async ({ ShopCustomer, StorefrontAccountLogin, IdProvider }) => {
            const locale = getLocale();
            const addressData = getAddressDataFromLocale(locale);

            const customer = {
                salutation: 'Mr.',
                firstName: 'Jeff',
                // lastName is missing intentionally
                email: `${IdProvider.getIdPair().uuid}@test.com`,
                password: 'shopware',
                street: addressData.street,
                city: addressData.city,
                country: addressData.country,
                postalCode: addressData.postalCode,
            };

            await ShopCustomer.goesTo(StorefrontAccountLogin.url());

            await StorefrontAccountLogin.salutationSelect.selectOption(customer.salutation);
            await StorefrontAccountLogin.firstNameInput.fill(customer.firstName);
            await StorefrontAccountLogin.registerEmailInput.fill(customer.email);
            await StorefrontAccountLogin.registerPasswordInput.fill(customer.password);

            await StorefrontAccountLogin.streetAddressInput.fill(customer.street);
            await StorefrontAccountLogin.postalCodeInput.fill(customer.postalCode);
            await StorefrontAccountLogin.cityInput.fill(customer.city);
            await StorefrontAccountLogin.countryInput.selectOption({ label: customer.country });

            await StorefrontAccountLogin.registerButton.click();

            await ShopCustomer.expects(StorefrontAccountLogin.lastNameInput).toHaveClass(/(^|\s)is-invalid(\s|$)/);

            await StorefrontAccountLogin.lastNameInput.fill('Goldblum');
            await StorefrontAccountLogin.registerButton.click();

            await StorefrontAccountLogin.page.waitForURL('**/account', { waitUntil: 'commit' });

            await ShopCustomer.expects(StorefrontAccountLogin.page.getByText(customer.email, { exact: true })).toBeVisible();
        },
    );
});
