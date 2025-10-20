import { test } from '@fixtures/AcceptanceTest';
import { acceptTechnicalRequiredCookiesWithRecaptcha, verifyRecaptchaProtectionNotice, verifyRecaptchaScriptNotLoaded, waitForRecaptchaScriptLoaded } from '../../helpers/recaptcha-helpers';

const reCaptcha_V2_site_key = '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI';
const reCaptcha_V2_secret_key = '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe';

test('As a customer, I can see the visible Google reCaptcha V2 is loaded and functional.',
    { tag: ['@Form', '@Captcha', '@Storefront'] },
    async ({
        ShopCustomer,
        StorefrontAccountLogin,
        TestDataService,
        InstanceMeta,
        acceptTechnicalRequiredCookies,
    }) => {

        test.skip(InstanceMeta.isSaaS, 'SaaS just support FriendlyCaptcha');

        await test.step('Configure reCAPTCHA V2', async () => {
            await TestDataService.setSystemConfig({
                'core.basicInformation.activeCaptchasV2': {
                    googleReCaptchaV2: {
                        name: 'googleReCaptchaV2',
                        isActive: true,
                        config: {
                            siteKey: reCaptcha_V2_site_key,
                            secretKey: reCaptcha_V2_secret_key,
                            invisible: false,
                        },
                    },
                },
            });
        });

        await ShopCustomer.goesTo(StorefrontAccountLogin.url());

        await verifyRecaptchaScriptNotLoaded(StorefrontAccountLogin.page, test, 'V2');

        await acceptTechnicalRequiredCookies();

        await waitForRecaptchaScriptLoaded(StorefrontAccountLogin.page);

        // For visible V2, we need to check specific elements after script loads
        const reCaptchaContainer = StorefrontAccountLogin.page.locator('.captcha-google-re-captcha-v2').first();
        const reCaptchaFrame = reCaptchaContainer.locator('iframe').first();
        const reCaptchaCheckbox = reCaptchaFrame.contentFrame().getByRole('checkbox', { name: `I'm not a robot` });

        await test.step('Verify the reCaptcha V2 is loaded and visible after cookie consent', async () => {
            await ShopCustomer.expects(reCaptchaFrame).toBeVisible();
            await ShopCustomer.expects(reCaptchaCheckbox).toBeVisible();
        });

        await test.step('Verify the reCaptcha V2 checkbox is functional', async () => {
            await reCaptchaCheckbox.click();
            await ShopCustomer.expects(reCaptchaCheckbox).toBeChecked();
        });
    }
);

test('As a customer, I can see the invisible Google reCaptcha V2 is loaded and shows the protection notice.',
    { tag: ['@Form', '@Captcha', '@Storefront'] },
    async ({
        ShopCustomer,
        StorefrontAccountLogin,
        TestDataService,
        InstanceMeta,
        acceptTechnicalRequiredCookies,
    }) => {

        test.skip(InstanceMeta.isSaaS, 'SaaS just support FriendlyCaptcha');

        await test.step('Configure invisible reCAPTCHA V2', async () => {
            await TestDataService.setSystemConfig({
                'core.basicInformation.activeCaptchasV2': {
                    googleReCaptchaV2: {
                        name: 'googleReCaptchaV2',
                        isActive: true,
                        config: {
                            siteKey: reCaptcha_V2_site_key,
                            secretKey: reCaptcha_V2_secret_key,
                            invisible: true,
                        },
                    },
                },
            });
        });

        await ShopCustomer.goesTo(StorefrontAccountLogin.url());

        await verifyRecaptchaScriptNotLoaded(StorefrontAccountLogin.page, test, 'V2');

        await acceptTechnicalRequiredCookiesWithRecaptcha(
            StorefrontAccountLogin.page,
            test,
            () => acceptTechnicalRequiredCookies(),
            'V2'
        );

        await verifyRecaptchaProtectionNotice(StorefrontAccountLogin.page, test, 'V2');
    }
);


test('As a customer, I can see the invisible Google reCaptcha V2 is loaded in the contact form.',
    { tag: ['@Form', '@Contact', '@Captcha', '@Storefront'] },
    async ({
        ShopCustomer,
        StorefrontHome,
        StorefrontContactForm,
        TestDataService,
        InstanceMeta,
        acceptTechnicalRequiredCookies,
    }) => {

        test.skip(InstanceMeta.isSaaS, 'SaaS just support FriendlyCaptcha');

        await test.step('Configure invisible reCAPTCHA V2', async () => {
            await TestDataService.setSystemConfig({
                'core.basicInformation.activeCaptchasV2': {
                    googleReCaptchaV2: {
                        name: 'googleReCaptchaV2',
                        isActive: true,
                        config: {
                            siteKey: reCaptcha_V2_site_key,
                            secretKey: reCaptcha_V2_secret_key,
                            invisible: true,
                        },
                    },
                },
            });
        });

        await test.step('Open the contact form modal on home page', async () => {
            await ShopCustomer.goesTo(StorefrontHome.url());

            await verifyRecaptchaScriptNotLoaded(StorefrontHome.page, test, 'V2');

            await acceptTechnicalRequiredCookies();

            await StorefrontHome.contactFormLink.click();
            await ShopCustomer.expects(StorefrontContactForm.cardTitle).toContainText('Contact');
        });

        await waitForRecaptchaScriptLoaded(StorefrontContactForm.page);
        await verifyRecaptchaProtectionNotice(StorefrontContactForm.page, test, 'V2');
    }
);
