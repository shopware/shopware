import { expect, test } from '@fixtures/AcceptanceTest';

const reCaptcha_V3_site_key = '6LeNJ-UqAAAAAPmLzX0ekQuuv7f4HR8FVyaF4FrR';
const reCaptcha_V3_secret_key = '6LeNJ-UqAAAAAGIxrxNBjVvQwPUZ6_DJxWlqXC9u';

test('As a customer, I can perform a registration by validating to be not a robot via the Google reCaptcha V3.',
    { tag: '@form @Registration @Captcha' },
    async ({
        ShopCustomer,
        StorefrontAccountLogin,
        StorefrontAccount,
        TestDataService,
        IdProvider,
        Register,
        InstanceMeta ,
    }) => {

        test.skip(InstanceMeta.isSaaS, 'SaaS just support FriendlyCaptcha');

        await TestDataService.setSystemConfig({
            'core.basicInformation.activeCaptchasV2': {
                googleReCaptchaV3: {
                    name: 'googleReCaptchaV3',
                    isActive: true,
                    config: {
                        siteKey: reCaptcha_V3_site_key,
                        secretKey: reCaptcha_V3_secret_key,
                        thresholdScore: 0.5,
                    },
                },
            },
        });

        const customer = { email: IdProvider.getIdPair().uuid + '@test.com' };

        await ShopCustomer.goesTo(StorefrontAccountLogin.url());

        const reCaptchaNotice = StorefrontAccountLogin.page.getByText('This site is protected by reCAPTCHA');

        await ShopCustomer.expects(reCaptchaNotice).toBeVisible();

        await test.step('Customer attempts to register and is automatically validated via the invisible reCaptcha V3', async () => {
            await ShopCustomer.attemptsTo(Register(customer));
            await ShopCustomer.expects(StorefrontAccount.page.getByText(customer.email, { exact: true })).toBeVisible();
        });
    }
);

test('As a customer, I can perform a registration that is validated by the invisible Google reCaptcha V3 even after a false input.',
    { tag: '@form @Registration @Captcha' },
    async ({
        ShopCustomer,
        StorefrontAccountLogin,
        StorefrontAccount,
        TestDataService,
        IdProvider,
        InstanceMeta ,
    }) => {

        test.skip(InstanceMeta.isSaaS, 'SaaS just support FriendlyCaptcha');

        await TestDataService.setSystemConfig({
            'core.basicInformation.activeCaptchasV2': {
                googleReCaptchaV3: {
                    name: 'googleReCaptchaV3',
                    isActive: true,
                    config: {
                        siteKey: reCaptcha_V3_site_key,
                        secretKey: reCaptcha_V3_secret_key,
                        thresholdScore: 0.5,
                    },
                },
            },
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

            const reCaptchaNotice = StorefrontAccountLogin.page.getByText('This site is protected by reCAPTCHA');
            await ShopCustomer.expects(reCaptchaNotice).toBeVisible();
        });

        await test.step('Customer attempts to register but forgets to fill out a required field', async () => {

            await StorefrontAccountLogin.salutationSelect.selectOption(customer.salutation);
            await StorefrontAccountLogin.firstNameInput.fill(customer.firstName);
            await StorefrontAccountLogin.registerEmailInput.fill(customer.email);
            await StorefrontAccountLogin.registerPasswordInput.fill(customer.password);

            await StorefrontAccountLogin.streetAddressInput.fill(customer.street);
            await StorefrontAccountLogin.postalCodeInput.fill(customer.postalCode);
            await StorefrontAccountLogin.cityInput.fill(customer.city);
            await StorefrontAccountLogin.countryInput.selectOption({ label: customer.country });

            await StorefrontAccountLogin.registerButton.click();

            /**
             * Submitting the form triggers a request to google to validate the captcha.
             * If we don't wait for this response the test will already have filled out the missing field and
             * the form will be valid by the time the request returns and will therefore already trigger a valid submit.
             */
            await StorefrontAccountLogin.page.waitForResponse(resp => resp.url().includes('google.com/recaptcha/api2/clr'));

            await ShopCustomer.expects(StorefrontAccountLogin.lastNameInput).toHaveClass(/(^|\s)is-invalid(\s|$)/);
        });

        await test.step('Customer fills out the missing field and re-attempts the registration', async() => {
            await StorefrontAccountLogin.lastNameInput.fill(customer.lastName);

            await StorefrontAccountLogin.registerButton.click();

            await ShopCustomer.expects(StorefrontAccount.page.getByText(customer.email, { exact: true })).toBeVisible();
        });
    }
);

test('As a customer, I want to fill out and submit the contact form that is validated by the invisible Google reCaptcha V3.',
    { tag: '@form @contact @Captcha' },
    async ({
        ShopCustomer,
        StorefrontHome,
        StorefrontContactForm,
        DefaultSalesChannel,
        TestDataService,
        InstanceMeta ,
    }) => {

        test.skip(InstanceMeta.isSaaS, 'SaaS just support FriendlyCaptcha');

        await TestDataService.setSystemConfig({
            'core.basicInformation.activeCaptchasV2': {
                googleReCaptchaV3: {
                    name: 'googleReCaptchaV3',
                    isActive: true,
                    config: {
                        siteKey: reCaptcha_V3_site_key,
                        secretKey: reCaptcha_V3_secret_key,
                        thresholdScore: 0.5,
                    },
                },
            },
        });

        await test.step('Open the contact form modal on home page.', async () => {
            await ShopCustomer.goesTo(StorefrontHome.url());
            await StorefrontHome.contactFormLink.click();
            await ShopCustomer.expects(StorefrontContactForm.cardTitle).toContainText('Contact');

            const reCaptchaNotice = StorefrontContactForm.page.getByText('This site is protected by reCAPTCHA');
            await ShopCustomer.expects(reCaptchaNotice).toBeVisible();
        });

        await test.step('Fill out all necessary contact information.', async () => {
            await StorefrontContactForm.salutationSelect.selectOption('Mr.');
            await StorefrontContactForm.firstNameInput.fill('John');
            await StorefrontContactForm.lastNameInput.fill('Doe');
            await StorefrontContactForm.emailInput.fill('mail@test.com');
            await StorefrontContactForm.phoneInput.fill('0123456789');
            await StorefrontContactForm.subjectInput.fill('Test: Product question');
            await StorefrontContactForm.commentInput.fill('Test: Hello, I have a question about your products.');
            await StorefrontContactForm.privacyPolicyCheckbox.click();
        });

        await test.step('Send and validate the contact form.', async () => {
            const contactFormPromise = StorefrontContactForm.page.waitForResponse(
                `${process.env['APP_URL'] + 'test-' + DefaultSalesChannel.salesChannel.id}/form/contact`
            );

            await StorefrontContactForm.submitButton.click();
            const contactFormResponse = await contactFormPromise;

            expect(contactFormResponse.ok()).toBeTruthy();

            await ShopCustomer.expects(StorefrontContactForm.contactSuccessMessage).toHaveText(
                'We have received your contact request and will process it as soon as possible.'
            );
        });
    }
);
