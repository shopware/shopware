import { test } from '@fixtures/AcceptanceTest';

// Annotate entire file as serial run.
test.describe.configure({ mode: 'serial' });
// https://developers.google.com/recaptcha/docs/faq
const reCAPTCHA_TEST_SITEKEY = '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI';

test(
    'As a customer, I expect to see and use the google recaptcha V2 on the account login page',
    { tag: '@form @Registration' },
    async ({ ShopCustomer, StorefrontAccountLogin, TestDataService, InstanceMeta }) => {

        test.skip(InstanceMeta.isSaaS, 'SaaS just support FriendlyCaptcha');

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
            },
        });

        await test.step('Open the account login page.', async () => {
            await ShopCustomer.goesTo(StorefrontAccountLogin.url());
        });

        await test.step('Validate the google recaptcha V2 is available.', async () => {
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaV2Container).toBeVisible();
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaV2Input).not.toBeVisible();
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaV2Input).toHaveCount(1);
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaProtectionInformation).not.toBeVisible();
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaProtectionInformation).toHaveCount(0);
        });
    }
);

test(
    'As a customer, I expect not to see it but use the google recaptcha V2 on the account login page.',
    { tag: '@form @Registration' },
    async ({ ShopCustomer, StorefrontAccountLogin, TestDataService, InstanceMeta }) => {

        test.skip(InstanceMeta.isSaaS, 'SaaS just support FriendlyCaptcha');

        await TestDataService.setSystemConfig({
            'core.basicInformation.activeCaptchasV2': {
                googleReCaptchaV2: {
                    name: 'googleReCaptchaV2',
                    isActive: true,
                    config: {
                        siteKey: reCAPTCHA_TEST_SITEKEY,
                        invisible: true,
                    },
                },
            },
        });

        await test.step('Open the account login page.', async () => {
            await ShopCustomer.goesTo(StorefrontAccountLogin.url());
        });

        await test.step('Validate the google recaptcha V2 is not visible but available.', async () => {
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaV2Container).not.toBeVisible();
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaV2Container).toHaveCount(1);
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaV2Input).not.toBeVisible();
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaV2Input).toHaveCount(1);
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaBadge).toHaveCount(1);
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaBadge).not.toBeVisible();
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaProtectionInformation).toBeVisible();
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaProtectionInformation).toHaveCount(1);
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaProtectionInformation).toContainText(
                'This site is protected by reCAPTCHA and the Google Privacy Policy and Terms of Service apply.'
            );
        });
    }
);

test(
    'As a customer, I expect to use the google recaptcha V3 on the account login page.',
    { tag: '@form @Registration' },
    async ({ ShopCustomer, StorefrontAccountLogin, TestDataService, InstanceMeta }) => {

        test.skip(InstanceMeta.isSaaS, 'SaaS just support FriendlyCaptcha');

        await TestDataService.setSystemConfig({
            'core.basicInformation.activeCaptchasV2': {
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

        await test.step('Open the account login page.', async () => {
            await ShopCustomer.goesTo(StorefrontAccountLogin.url());
        });

        await test.step('Validate the google recaptcha V3 is not visible but available.', async () => {
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaV2Container).not.toBeVisible();
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaV2Container).toHaveCount(0);
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaV2Input).not.toBeVisible();
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaV2Input).toHaveCount(0);
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaV3Input).toHaveCount(1);
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaV3Input).not.toBeVisible();
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaBadge).toHaveCount(1);
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaBadge).not.toBeVisible();
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaProtectionInformation).toBeVisible();
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaProtectionInformation).toHaveCount(1);
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaProtectionInformation).toContainText(
                'This site is protected by reCAPTCHA and the Google Privacy Policy and Terms of Service apply.'
            );
        });
    }
);

test(
    'As a customer, I expect not to see it but use the google recaptcha V2 and V3 on the account login page.',
    { tag: '@form @Registration' },
    async ({ ShopCustomer, StorefrontAccountLogin, TestDataService, InstanceMeta }) => {

        test.skip(InstanceMeta.isSaaS, 'SaaS just support FriendlyCaptcha');

        await TestDataService.setSystemConfig({
            'core.basicInformation.activeCaptchasV2': {
                googleReCaptchaV2: {
                    name: 'googleReCaptchaV2',
                    isActive: true,
                    config: {
                        siteKey: reCAPTCHA_TEST_SITEKEY,
                        invisible: true,
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

        await test.step('Open the account login page.', async () => {
            await ShopCustomer.goesTo(StorefrontAccountLogin.url());
        });

        await test.step('Validate the google recaptcha V2 and V3 are available but not visible.', async () => {
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaV2Container).not.toBeVisible();
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaV2Container).toHaveCount(1);
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaV2Input).not.toBeVisible();
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaV2Input).toHaveCount(1);
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaV3Input).toHaveCount(1);
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaV3Input).not.toBeVisible();
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaBadge).toHaveCount(2);
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaBadge.nth(0)).not.toBeVisible();
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaBadge.nth(1)).not.toBeVisible();
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaProtectionInformation).toBeVisible();
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaProtectionInformation).toHaveCount(1);
            await ShopCustomer.expects(StorefrontAccountLogin.greCaptchaProtectionInformation).toContainText(
                'This site is protected by reCAPTCHA and the Google Privacy Policy and Terms of Service apply.'
            );
        });
    }
);
