import { test } from '@fixtures/AcceptanceTest';
import { verifyRecaptchaProtectionNotice, verifyRecaptchaScriptNotLoaded, waitForRecaptchaScriptLoaded } from '../../helpers/recaptcha-helpers';

const reCaptcha_V2_site_key = '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI';
const reCaptcha_V2_secret_key = '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe';

// Store original config for cleanup
let originalConfig: Record<string, unknown> = {};

test.describe('Google reCAPTCHA V2 Login Tests', () => {
    test.beforeEach(async ({ TestDataService, InstanceMeta }) => {
        test.skip(InstanceMeta.isSaaS, 'SaaS just support FriendlyCaptcha');

        // Get and store current config
        const getCurrentConfig = await TestDataService.AdminApiClient.get('_action/system-config?domain=core.basicInformation');
        if (!getCurrentConfig.ok()) {
            throw new Error(`Failed to get system config: ${getCurrentConfig.status()} ${getCurrentConfig.statusText()}`);
        }
        originalConfig = await getCurrentConfig.json();

        // Merge in the googleReCaptchaV2 settings
        const updatedConfig = {
            null: {
                ...(originalConfig as Record<string, unknown>),
                'core.basicInformation.activeCaptchasV2': {
                    ...((originalConfig['core.basicInformation.activeCaptchasV2'] as Record<string, unknown>) || {}),
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
            },
        };

        // Post the complete merged config
        const updateResponse = await TestDataService.AdminApiClient.post('_action/system-config/batch', {
            data: updatedConfig
        });
        if (!updateResponse.ok()) {
            throw new Error(`Failed to update system config: ${updateResponse.status()} ${updateResponse.statusText()}`);
        }

        // Clear cache to ensure config takes effect
        const cacheResponse = await TestDataService.AdminApiClient.delete('_action/cache');
        if (!cacheResponse.ok()) {
            throw new Error(`Failed to clear cache: ${cacheResponse.status()} ${cacheResponse.statusText()}`);
        }

        // Verify the config was actually set
        const verifyResponse = await TestDataService.AdminApiClient.get('_action/system-config?domain=core.basicInformation');
        if (!verifyResponse.ok()) {
            throw new Error(`Failed to verify system config: ${verifyResponse.status()} ${verifyResponse.statusText()}`);
        }
        const verifyConfig = await verifyResponse.json();

        const captchaActive = verifyConfig['core.basicInformation.activeCaptchasV2']?.googleReCaptchaV2?.isActive;
        if (!captchaActive) {
            throw new Error('Failed to configure reCAPTCHA V2: isActive is not true');
        }
    });

    test.afterEach(async ({ TestDataService }) => {
        // Restore original config
        if (Object.keys(originalConfig).length > 0) {
            await TestDataService.AdminApiClient.post('_action/system-config/batch', {
                data: { null: originalConfig }
            });

            // Clear cache after restoring
            await TestDataService.AdminApiClient.delete('_action/cache');
        }
    });

    test('As a customer, I can perform a registration by validating to be not a robot via the invisible Google reCaptcha V2.',
        { tag: ['@Form', '@Registration', '@Captcha', '@Storefront'] },
        async ({
            ShopCustomer,
            StorefrontAccountLogin,
            StorefrontAccount,
            IdProvider,
            Register,
            acceptTechnicalRequiredCookies,
        }) => {

            const customer = { email: `${IdProvider.getIdPair().uuid}@test.com` };

            await test.step('Navigate to login page and verify initial state', async () => {
                await ShopCustomer.goesTo(StorefrontAccountLogin.url());
                await verifyRecaptchaScriptNotLoaded(StorefrontAccountLogin.page, test, 'V2');
            });

            await test.step('Accept cookies and verify _GRECAPTCHA cookie is registered', async () => {
                const promiseCookieGroupsRequest = StorefrontAccountLogin.page.waitForResponse(
                    resp => resp.url().includes('cookie/groups')
                );

                await acceptTechnicalRequiredCookies();

                const cookieGroupsResponse = await promiseCookieGroupsRequest;
                const cookieGroups = await cookieGroupsResponse.json();
                const technicalRequiredCookies = cookieGroups.elements.find(group => group.name === 'Technically required');
                const grecaptchaEntry = technicalRequiredCookies?.entries?.find(entry => entry.cookie === '_GRECAPTCHA');

                ShopCustomer.expects(grecaptchaEntry).toBeTruthy();

                await waitForRecaptchaScriptLoaded(StorefrontAccountLogin.page);
                await verifyRecaptchaProtectionNotice(StorefrontAccountLogin.page, test, 'V2');
            });

            await test.step('Customer attempts to register and is automatically validated via the invisible reCaptcha V2', async () => {
                await ShopCustomer.attemptsTo(Register(customer));
                await ShopCustomer.expects(StorefrontAccount.page.getByText(customer.email, { exact: true })).toBeVisible();
            });
        }
    );
});
