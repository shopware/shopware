import { expect, test } from '@fixtures/AcceptanceTest';
import { satisfies } from 'compare-versions';

const reCaptcha_V3_site_key = '6LeNJ-UqAAAAAPmLzX0ekQuuv7f4HR8FVyaF4FrR';
const reCaptcha_V3_secret_key = '6LeNJ-UqAAAAAGIxrxNBjVvQwPUZ6_DJxWlqXC9u';

test('As a customer, I can perform a registration by validating to be not a robot via the Google reCaptcha V3.',
    { tag: ['@Form', '@Registration', '@Captcha', '@Storefront'] },
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

        await TestDataService.setSystemConfig({
            'core.basicInformation.activeCaptchasV2': {
                googleReCaptchaV3: {
                    name: 'googleReCaptchaV3',
                    isActive: true,
                    config: {
                        siteKey: reCaptcha_V3_site_key,
                        secretKey: reCaptcha_V3_secret_key,
                        thresholdScore: 0.0,
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
    { tag: ['@Form', '@Registration', '@Captcha', '@Storefront'] },
    async ({
        ShopCustomer,
        StorefrontAccountLogin,
        StorefrontAccount,
        TestDataService,
        IdProvider,
        InstanceMeta,
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
                        thresholdScore: 0.0,
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
            country: 'United Kingdom',
            postalCode: '48624',
        };

        await test.step('Customer goes to registration page', async () => {
            await ShopCustomer.goesTo(StorefrontAccountLogin.url());

            const reCaptchaNotice = StorefrontAccountLogin.page.getByText('This site is protected by reCAPTCHA');
            await ShopCustomer.expects(reCaptchaNotice).toBeVisible();
        });

        await test.step('Customer attempts to register but forgets to fill out a required field', async () => {

            await ShopCustomer.presses(StorefrontAccountLogin.salutationSelect);
            await StorefrontAccountLogin.salutationSelect.selectOption(customer.salutation);
            await ShopCustomer.fillsIn(StorefrontAccountLogin.firstNameInput, customer.firstName);
            await ShopCustomer.fillsIn(StorefrontAccountLogin.registerEmailInput, customer.email);
            await ShopCustomer.fillsIn(StorefrontAccountLogin.registerPasswordInput, customer.password);

            await ShopCustomer.fillsIn(StorefrontAccountLogin.streetAddressInput, customer.street);
            await ShopCustomer.fillsIn(StorefrontAccountLogin.postalCodeInput, customer.postalCode);
            await ShopCustomer.fillsIn(StorefrontAccountLogin.cityInput, customer.city);
            await ShopCustomer.presses(StorefrontAccountLogin.countryInput);
            await StorefrontAccountLogin.countryInput.selectOption({ label: customer.country });

            await ShopCustomer.presses(StorefrontAccountLogin.registerButton);

            /**
             * Submitting the form triggers a request to google to validate the captcha.
             * If we don't wait for this response the test will already have filled out the missing field and
             * the form will be valid by the time the request returns and will therefore already trigger a valid submit.
             */
            await StorefrontAccountLogin.page.waitForResponse(resp => resp.url().includes('google.com/recaptcha/api2/clr'));


            // eslint-disable-next-line playwright/no-conditional-in-test
            if (!InstanceMeta.features['ACCESSIBILITY_TWEAKS'] && satisfies(InstanceMeta.version, '<6.7')) {
                await ShopCustomer.expects(StorefrontAccountLogin.lastNameInput).toHaveClass('form-control');
            } else {
                await ShopCustomer.expects(StorefrontAccountLogin.lastNameInput).toHaveClass(/(^|\s)is-invalid(\s|$)/);
            }
        });

        await test.step('Customer fills out the missing field and re-attempts the registration', async () => {
            await ShopCustomer.fillsIn(StorefrontAccountLogin.lastNameInput, customer.lastName);

            await ShopCustomer.presses(StorefrontAccountLogin.registerButton);
            // await StorefrontAccount.page.waitForURL('**/account', { waitUntil: 'commit' });

            await ShopCustomer.expects(StorefrontAccount.page.getByText(customer.email, { exact: true })).toBeVisible();
        });
    }
);

test('As a customer, I want to fill out and submit the contact form that is validated by the invisible Google reCaptcha V3.',
    { tag: ['@Form', '@Contact', '@Captcha', '@Storefront'] },
    async ({
        ShopCustomer,
        StorefrontHome,
        StorefrontContactForm,
        DefaultSalesChannel,
        TestDataService,
        InstanceMeta,
        StorefrontFooter,
    }) => {

        test.skip(InstanceMeta.isSaaS, 'SaaS just support FriendlyCaptcha');
        test.slow(); // Necessary for multiple retries due to rate limiting

        await TestDataService.setSystemConfig({
            'core.basicInformation.activeCaptchasV2': {
                googleReCaptchaV3: {
                    name: 'googleReCaptchaV3',
                    isActive: true,
                    config: {
                        siteKey: reCaptcha_V3_site_key,
                        secretKey: reCaptcha_V3_secret_key,
                        thresholdScore: 0.0,
                    },
                },
            },
        });

        await test.step('Open the contact form modal on home page.', async () => {
            await ShopCustomer.goesTo(StorefrontHome.url());
            await ShopCustomer.presses(StorefrontFooter.footerContactFormLink);
            await ShopCustomer.expects(StorefrontContactForm.cardTitle).toContainText('Contact');

            const reCaptchaNotice = StorefrontContactForm.page.getByText('This site is protected by reCAPTCHA');
            await ShopCustomer.expects(reCaptchaNotice).toBeVisible();
        });

        await test.step('Fill out all necessary contact information.', async () => {
            await ShopCustomer.presses(StorefrontContactForm.salutationSelect);
            await StorefrontContactForm.salutationSelect.selectOption('Mr.');
            await ShopCustomer.fillsIn(StorefrontContactForm.firstNameInput, 'John');
            await ShopCustomer.fillsIn(StorefrontContactForm.lastNameInput, 'Doe');
            await ShopCustomer.fillsIn(StorefrontContactForm.emailInput, 'mail@test.com');
            await ShopCustomer.fillsIn(StorefrontContactForm.phoneInput, '0123456789');
            await ShopCustomer.fillsIn(StorefrontContactForm.subjectInput, 'Test: Product question');
            await ShopCustomer.fillsIn(StorefrontContactForm.commentInput, 'Test: Hello, I have a question about your products.');
            await ShopCustomer.presses(StorefrontContactForm.privacyPolicyCheckbox);
        });

        await ShopCustomer.expects(async () => {
            await test.step('Send and validate the contact form.', async () => {
                const contactFormPromise = StorefrontContactForm.page.waitForResponse(
                    `${process.env['APP_URL'] + 'test-' + DefaultSalesChannel.salesChannel.id}/form/contact`
                );

                await ShopCustomer.presses(StorefrontContactForm.submitButton);
                const contactFormResponse = await contactFormPromise;

                expect(contactFormResponse.ok()).toBeTruthy();

                await ShopCustomer.expects(StorefrontContactForm.contactSuccessMessage).toHaveText(
                    'We have received your contact request and will process it as soon as possible.'
                );
            });
        }).toPass({
            intervals: [30_000], // retry after 30 seconds
        });
    }
);
