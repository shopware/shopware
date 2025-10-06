import { test } from '@fixtures/AcceptanceTest';

const reCaptcha_V2_site_key = '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI';
const reCaptcha_V2_secret_key = '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe';

test('As a customer, I can perform a registration by validating to be not a robot via the invisible Google reCaptcha V2.',
    { tag: ['@Form', '@Registration', '@Captcha', '@Storefront'] },
    async ({
        ShopCustomer,
        StorefrontAccountLogin,
        StorefrontAccount,
        TestDataService,
        IdProvider,
        Register,
        InstanceMeta,
        acceptTechnicalRequiredCookies,
    }) => {

        test.skip(InstanceMeta.isSaaS, 'SaaS just support FriendlyCaptcha');

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
        await TestDataService.AdminApiClient.delete('./_action/cache-delayed');

        const customer = { email: `${IdProvider.getIdPair().uuid}@test.com` };

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

        const promiseCookieGroupsRequest = StorefrontAccountLogin.page.waitForResponse(resp => resp.url().includes('cookie/groups'));
        const cookieGroupsResponse = await promiseCookieGroupsRequest;
        const cookieGroups = await cookieGroupsResponse.json();
        const technicalRequiredCookies = cookieGroups.elements.find(group => group.name === 'Technically required');

        console.log(technicalRequiredCookies.entries);
        ShopCustomer.expects(technicalRequiredCookies.entries.find(entry => entry.cookie === '_GRECAPTCHA')).toBeTruthy();

        await ShopCustomer.page.waitForLoadState('networkidle');
        await ShopCustomer.page.waitForSelector('iframe[src*="recaptcha"]', { state: 'attached' });

        await test.step('Verify the invisible reCaptcha V2 is loaded and shows protection notice', async () => {
            const reCaptchaNotice = StorefrontAccountLogin.page.getByText('This site is protected by reCAPTCHA');
            await ShopCustomer.expects(reCaptchaNotice).toBeVisible();
        });

        await test.step('Customer attempts to register and is automatically validated via the invisible reCaptcha V2', async () => {
            await ShopCustomer.attemptsTo(Register(customer));
            await ShopCustomer.expects(StorefrontAccount.page.getByText(customer.email, { exact: true })).toBeVisible();
        });
    }
);
