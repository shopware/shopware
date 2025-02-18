import { test, expect } from '@fixtures/AcceptanceTest';
import { satisfies } from 'compare-versions';

test(
    'As a customer, I expect to see and use a basic captcha function on the contact form.',
    { tag: '@form @contact' },
    async ({ ShopCustomer, StorefrontHome, StorefrontContactForm, DefaultSalesChannel, TestDataService, InstanceMeta }) => {

        test.skip(InstanceMeta.isSaaS, 'SaaS just support FriendlyCaptcha');

        await TestDataService.setSystemConfig({'core.basicInformation.activeCaptchasV2': {'basicCaptcha': { 'name': 'basicCaptcha', 'isActive': true }} });

        await test.step('Open the contact form modal on home page.', async () => {
            await ShopCustomer.goesTo(StorefrontHome.url());
            await StorefrontHome.contactFormLink.click();
            await ShopCustomer.expects(StorefrontContactForm.cardTitle).toContainText('Contact');
        });

        await test.step('Fill out all necessary contact information.', async () => {
            await StorefrontContactForm.salutationSelect.selectOption('Mr.');
            await StorefrontContactForm.firstNameInput.fill('John');
            await StorefrontContactForm.lastNameInput.fill('Doe');
            await StorefrontContactForm.emailInput.fill('mail@test.com');
            await StorefrontContactForm.phoneInput.fill('0123456789');
            await StorefrontContactForm.subjectInput.fill('Test: Product question');
            await StorefrontContactForm.commentInput.fill('Test: Hello, I have a question about your products.');
            await StorefrontContactForm.basicCaptchaInput.fill('1234');
            await StorefrontContactForm.privacyPolicyCheckbox.click();
        });

        await test.step('Validate the basic captcha is available.', async () => {
            await ShopCustomer.expects(StorefrontContactForm.basicCaptcha).toBeVisible();
            await ShopCustomer.expects(StorefrontContactForm.basicCaptchaImage).toBeVisible();
            await ShopCustomer.expects(StorefrontContactForm.basicCaptchaRefreshButton).toBeVisible();
        });

        await test.step('Send and validate the unaccomplished contact form.', async () => {
            const contactFormPromise = StorefrontContactForm.page.waitForResponse(
                `${process.env['APP_URL'] + 'test-' + DefaultSalesChannel.salesChannel.id}/form/contact`
            );
            await StorefrontContactForm.submitButton.click();
            const contactFormResponse = await contactFormPromise;
            expect(contactFormResponse.ok()).toBeTruthy();

            await ShopCustomer.expects(StorefrontContactForm.basicCaptchaInput).toHaveCSS('border-color', 'rgb(194, 0, 23)');

            // eslint-disable-next-line playwright/no-conditional-in-test
            if (InstanceMeta.features['ACCESSIBILITY_TWEAKS']) {
                await ShopCustomer.expects(StorefrontContactForm.formAlert).toBeVisible();
                await ShopCustomer.expects(StorefrontContactForm.formAlert).toContainText('Incorrect input. Please try again.');
            }
        });
    }
);

test(
    'As a customer, I expect to see and use the google recaptcha V2 and V3 function on the contact form.',
    { tag: '@form @contact' },
    async ({ ShopCustomer, StorefrontHome, StorefrontContactForm, TestDataService, InstanceMeta }) => {

        test.skip(InstanceMeta.isSaaS, 'SaaS just support FriendlyCaptcha');

        // https://developers.google.com/recaptcha/docs/faq
        const reCAPTCHA_TEST_SITEKEY = '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI';

        await TestDataService.setSystemConfig({
            'core.basicInformation.activeCaptchasV2': {
                googleReCaptchaV2: {
                    name: 'googleReCaptchaV2',
                    isActive: true,
                    config: {
                        siteKey: reCAPTCHA_TEST_SITEKEY,
                        invisible: false,
                    },
                },
                googleReCaptchaV3: {
                    name: 'googleReCaptchaV3',
                    isActive: true,
                    config: {
                        siteKey: reCAPTCHA_TEST_SITEKEY,
                        thresholdScore: 0.5,
                    },
                },
            },
        });

        await test.step('Open the contact form modal on home page.', async () => {
            await ShopCustomer.goesTo(StorefrontHome.url());
            await StorefrontHome.contactFormLink.click();
            await ShopCustomer.expects(StorefrontContactForm.cardTitle).toContainText('Contact');
        });

        await test.step('Validate the google recaptcha is available.', async () => {
            await ShopCustomer.expects(StorefrontContactForm.greCaptchaV2Container).toBeVisible();
            await ShopCustomer.expects(StorefrontContactForm.greCaptchaV2Input).not.toBeVisible();
            await ShopCustomer.expects(StorefrontContactForm.greCaptchaV2Input).toHaveCount(1);
            await ShopCustomer.expects(StorefrontContactForm.greCaptchaProtectionInformation).toBeVisible();
            await ShopCustomer.expects(StorefrontContactForm.greCaptchaProtectionInformation).toHaveCount(1);
            await ShopCustomer.expects(StorefrontContactForm.greCaptchaProtectionInformation).toContainText(
                'This site is protected by reCAPTCHA and the Google Privacy Policy and Terms of Service apply.'
            );
        });
    }
);
