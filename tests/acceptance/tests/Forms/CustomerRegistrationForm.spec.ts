import { test } from '@fixtures/AcceptanceTest';

test('As a customer, I can perform a registration without captcha protection.',
    { tag: ['@Form', '@Registration', '@Storefront'] },
    async ({
        ShopCustomer,
        StorefrontAccountLogin,
        StorefrontAccount,
        TestDataService,
        IdProvider,
        Register,
        InstanceMeta,
    }) => {

        test.skip(InstanceMeta.isSaaS, 'SaaS just support FriendlyCaptcha');

        await test.step('Disable captcha protection', async () => {
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

        const customer = { email: `${IdProvider.getIdPair().uuid}@test.com` };

        await test.step('Customer goes to registration page', async () => {
            await ShopCustomer.goesTo(StorefrontAccountLogin.url());
        });

        await test.step('Customer registers successfully', async () => {
            await ShopCustomer.attemptsTo(Register(customer));

            // Wait for navigation or registration to complete
            await StorefrontAccountLogin.page.waitForLoadState('networkidle');

            // Look for the customer email on the page
            await ShopCustomer.expects(StorefrontAccount.page.getByText(customer.email, { exact: true })).toBeVisible();
        });
    }
);

test('As a customer, I can perform a registration with full customer data without captcha protection.',
    { tag: ['@Form', '@Registration', '@Storefront'] },
    async ({
        ShopCustomer,
        StorefrontAccountLogin,
        StorefrontAccount,
        TestDataService,
        IdProvider,
        InstanceMeta,
    }) => {

        test.skip(InstanceMeta.isSaaS, 'SaaS just support FriendlyCaptcha');

        await test.step('Disable captcha protection', async () => {
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

        const customer = {
            salutation: 'Mr.',
            firstName: 'Jeff',
            lastName: 'Goldblum',
            email: `${IdProvider.getIdPair().uuid}@test.com`,
            password: 'shopware',
            street: 'Ebbinghof 10',
            city: 'Schöppingen',
            country: 'Germany',
            postalCode: '48624',
        };

        await test.step('Customer goes to registration page', async () => {
            await ShopCustomer.goesTo(StorefrontAccountLogin.url());
        });

        await test.step('Customer fills out all registration fields', async () => {
            await StorefrontAccountLogin.salutationSelect.selectOption(customer.salutation);
            await StorefrontAccountLogin.firstNameInput.fill(customer.firstName);
            await StorefrontAccountLogin.lastNameInput.fill(customer.lastName);
            await StorefrontAccountLogin.registerEmailInput.fill(customer.email);
            await StorefrontAccountLogin.registerPasswordInput.fill(customer.password);

            await StorefrontAccountLogin.streetAddressInput.fill(customer.street);
            await StorefrontAccountLogin.postalCodeInput.fill(customer.postalCode);
            await StorefrontAccountLogin.cityInput.fill(customer.city);
            await StorefrontAccountLogin.countryInput.selectOption({ label: customer.country });
        });

        await test.step('Customer submits registration form', async () => {
            await StorefrontAccountLogin.registerButton.click();

            // Wait for navigation or registration to complete
            await StorefrontAccountLogin.page.waitForLoadState('networkidle');

            // Look for the customer email on the page
            await ShopCustomer.expects(StorefrontAccount.page.getByText(customer.email, { exact: true })).toBeVisible();
        });
    }
);

test('As a customer, I can perform a registration with validation errors without captcha protection.',
    { tag: ['@Form', '@Registration', '@Storefront'] },
    async ({
        ShopCustomer,
        StorefrontAccountLogin,
        TestDataService,
        IdProvider,
        InstanceMeta,
    }) => {

        test.skip(InstanceMeta.isSaaS, 'SaaS just support FriendlyCaptcha');

        await test.step('Disable captcha protection', async () => {
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

        const customer = {
            salutation: 'Mr.',
            firstName: 'Jeff',
            // lastName is missing intentionally
            email: `${IdProvider.getIdPair().uuid}@test.com`,
            password: 'shopware',
            street: 'Ebbinghof 10',
            city: 'Schöppingen',
            country: 'Germany',
            postalCode: '48624',
        };

        await test.step('Customer goes to registration page', async () => {
            await ShopCustomer.goesTo(StorefrontAccountLogin.url());
        });

        await test.step('Customer attempts to register with missing required field', async () => {
            await StorefrontAccountLogin.salutationSelect.selectOption(customer.salutation);
            await StorefrontAccountLogin.firstNameInput.fill(customer.firstName);
            await StorefrontAccountLogin.registerEmailInput.fill(customer.email);
            await StorefrontAccountLogin.registerPasswordInput.fill(customer.password);

            await StorefrontAccountLogin.streetAddressInput.fill(customer.street);
            await StorefrontAccountLogin.postalCodeInput.fill(customer.postalCode);
            await StorefrontAccountLogin.cityInput.fill(customer.city);
            await StorefrontAccountLogin.countryInput.selectOption({ label: customer.country });

            await StorefrontAccountLogin.registerButton.click();

            // Verify validation error is shown
            await ShopCustomer.expects(StorefrontAccountLogin.lastNameInput).toHaveClass(/(^|\s)is-invalid(\s|$)/);
        });

        await test.step('Customer fills out the missing field and re-attempts the registration', async () => {
            await StorefrontAccountLogin.lastNameInput.fill('Goldblum');

            await StorefrontAccountLogin.registerButton.click();

            // Wait for navigation or registration to complete
            await StorefrontAccountLogin.page.waitForLoadState('networkidle');

            // Look for the customer email on the page
            await ShopCustomer.expects(StorefrontAccountLogin.page.getByText(customer.email, { exact: true })).toBeVisible();
        });
    }
);
