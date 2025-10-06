import { test } from '@fixtures/AcceptanceTest';
import { verifyRecaptchaProtectionNotice, verifyRecaptchaScriptNotLoaded, waitForRecaptchaScriptLoaded } from '../../helpers/recaptcha-helpers';

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

        await verifyRecaptchaScriptNotLoaded(StorefrontAccountLogin.page, test, 'V2');

        const promiseCookieGroupsRequest = StorefrontAccountLogin.page.waitForResponse(
            resp => resp.url().includes('cookie/groups')
        );

        await acceptTechnicalRequiredCookies(StorefrontAccountLogin);
        await waitForRecaptchaScriptLoaded(StorefrontAccountLogin.page);

        const cookieGroupsResponse = await promiseCookieGroupsRequest;
        const cookieGroups = await cookieGroupsResponse.json();
        const technicalRequiredCookies = cookieGroups.elements.find(group => group.name === 'Technically required');

        console.log(technicalRequiredCookies.entries);
        ShopCustomer.expects(technicalRequiredCookies.entries.find(entry => entry.cookie === '_GRECAPTCHA')).toBeTruthy();

        await verifyRecaptchaProtectionNotice(StorefrontAccountLogin.page, test, 'V2');

        await test.step('Customer attempts to register and is automatically validated via the invisible reCaptcha V2', async () => {
            await ShopCustomer.attemptsTo(Register(customer));
            await ShopCustomer.expects(StorefrontAccount.page.getByText(customer.email, { exact: true })).toBeVisible();
        });
    }
);
