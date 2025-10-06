import { test } from '@fixtures/AcceptanceTest';

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

        const reCaptchaContainer = StorefrontAccountLogin.page.locator('.captcha-google-re-captcha-v2').first();
        const reCaptchaInput = reCaptchaContainer.locator('.grecaptcha-v2-input').first();
        const reCaptchaFrame = reCaptchaContainer.locator('iframe').first();
        const reCaptchaCheckbox = reCaptchaFrame.contentFrame().getByRole('checkbox', { name: `I'm not a robot` });

        await test.step('Verify the reCaptcha V2 is not loaded before cookie consent', async () => {
            await ShopCustomer.expects(reCaptchaInput).not.toBeVisible();
            await ShopCustomer.expects(reCaptchaFrame).not.toBeVisible();
            await ShopCustomer.expects(reCaptchaCheckbox).not.toBeVisible();
        });

        await acceptTechnicalRequiredCookies(StorefrontAccountLogin);

        await ShopCustomer.page.waitForLoadState('networkidle');
        await ShopCustomer.page.waitForSelector('iframe[src*="recaptcha"]', { state: 'attached' });

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

        await acceptTechnicalRequiredCookies(StorefrontAccountLogin);

        await test.step('Verify the invisible reCaptcha V2 is loaded and shows protection notice', async () => {
            const reCaptchaNotice = StorefrontAccountLogin.page.getByText('This site is protected by reCAPTCHA');
            await ShopCustomer.expects(reCaptchaNotice).toBeVisible();
        });
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

            await test.step('Verify reCaptcha script is not loaded before cookie consent', async () => {
                const reCaptchaScript = StorefrontHome.page.locator('#recaptcha-script');
                await ShopCustomer.expects(reCaptchaScript).toHaveAttribute('data-src');
                await ShopCustomer.expects(reCaptchaScript).not.toHaveAttribute('src');
            });

            await acceptTechnicalRequiredCookies(StorefrontHome);

            await StorefrontHome.contactFormLink.click();
            await ShopCustomer.expects(StorefrontContactForm.cardTitle).toContainText('Contact');
        });

        await test.step('Verify the invisible reCaptcha V2 is loaded and shows protection notice', async () => {
            const reCaptchaNotice = StorefrontContactForm.page.getByText('This site is protected by reCAPTCHA');
            await ShopCustomer.expects(reCaptchaNotice).toBeVisible();

            // Wait for reCaptcha iframe to be loaded
            await StorefrontContactForm.page.waitForSelector('iframe[src*="recaptcha"]', { state: 'attached' });
        });
    }
);
