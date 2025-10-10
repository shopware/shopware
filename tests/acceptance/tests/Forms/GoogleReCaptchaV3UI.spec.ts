import { test } from '@fixtures/AcceptanceTest';
import { setupRecaptchaFlow, verifyRecaptchaProtectionNotice, verifyRecaptchaScriptNotLoaded, waitForRecaptchaScriptLoaded } from '../../helpers/recaptcha-helpers';

const reCaptcha_V3_site_key = '6LeNJ-UqAAAAAPmLzX0ekQuuv7f4HR8FVyaF4FrR';
const reCaptcha_V3_secret_key = '6LeNJ-UqAAAAAGIxrxNBjVvQwPUZ6_DJxWlqXC9u';

test('As a customer, I can see the invisible Google reCaptcha V3 is loaded and shows the protection notice.',
    { tag: ['@Form', '@Captcha', '@Storefront'] },
    async ({
        ShopCustomer,
        StorefrontAccountLogin,
        TestDataService,
        InstanceMeta,
        acceptTechnicalRequiredCookies,
    }) => {

        test.skip(InstanceMeta.isSaaS, 'SaaS just support FriendlyCaptcha');

        await test.step('Configure invisible reCAPTCHA V3', async () => {
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
        });

        await ShopCustomer.goesTo(StorefrontAccountLogin.url());

        await setupRecaptchaFlow(
            StorefrontAccountLogin.page,
            test,
            () => acceptTechnicalRequiredCookies(),
            'V3'
        );
    }
);


test('As a customer, I can see the invisible Google reCaptcha V3 is loaded in the contact form.',
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

        await test.step('Configure invisible reCAPTCHA V3', async () => {
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
        });

        await test.step('Open the contact form modal on home page', async () => {
            await ShopCustomer.goesTo(StorefrontHome.url());

            await verifyRecaptchaScriptNotLoaded(StorefrontHome.page, test, 'V3');

            await acceptTechnicalRequiredCookies();

            await StorefrontHome.contactFormLink.click();
            await ShopCustomer.expects(StorefrontContactForm.cardTitle).toContainText('Contact');
        });

        await waitForRecaptchaScriptLoaded(StorefrontContactForm.page);
        await verifyRecaptchaProtectionNotice(StorefrontContactForm.page, test, 'V3');
    }
);
