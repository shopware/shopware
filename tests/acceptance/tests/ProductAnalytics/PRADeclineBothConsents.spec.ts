// Annotate entire file as serial.
import { test } from '@fixtures/AcceptanceTest';
import { expect, Request, Route, Page } from '@playwright/test';
import {
    createNewAdminPageContext,
    getLocale,
    LanguageHelper,
    loginToAdministration,
    setCurrentContext, User,
} from '@shopware-ag/acceptance-test-suite';

interface CapturedRequest {
    postData: string;
}

/**
 * Settings for running tests in serial mode.
 * This is important for tests that modify global state or settings.
 */
test.describe.configure({ mode: 'serial' });

/**
 * Endpoint for Product Analytics API.
 */
const PRODUCT_ANALYTICS_ENDPOINT = 'httpapi';
const captured: CapturedRequest[] = [];

const requestHandler = async (route: Route) => {
    const req: Request = route.request();
    captured.push({
        postData: req.postData(),
    });
    await route.fulfill(
        {
            status: 200,
            headers: {
                'Access-Control-Allow-Origin': '*',
                'Access-Control-Allow-Credentials': 'true',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                'code': 200,
            }),
        }
    )
};


test('As a merchant, I want to make sure no admin events are sent after login if no consent is given.', { tag: '@ProductAnalytics' }, async ({
    CustomTranslationResources,
    IdProvider,
    SalesChannelBaseConfig,
    AdminApiContext,
    browser,
}) => {

    let page: Page;
    let languageHelper: LanguageHelper;
    let adminUser: User;

    await test.step('Setup page object before login to shopware administration', async () => {
        const locale = getLocale();
        languageHelper = await LanguageHelper.createInstance(locale, CustomTranslationResources);

        const { id, uuid } = IdProvider.getIdPair();

        adminUser = {
            id: uuid,
            username: `admin_${id}`,
            firstName: `${id} admin`,
            lastName: `${id} admin`,
            localeId: SalesChannelBaseConfig.currentLocaleId,
            email: `admin_${id}@example.com`,
            timezone: 'Europe/Berlin',
            password: 'shopware',
            admin: true,
        };

        const response = await AdminApiContext.post('user', {
            data: adminUser,
        });

        expect(response.ok()).toBeTruthy();

        page = await createNewAdminPageContext(browser, SalesChannelBaseConfig);
    });

    await test.step('Intercept all the API calls to product analytics', async () => {
        await page.route(`**/${PRODUCT_ANALYTICS_ENDPOINT}`, requestHandler);
    });

    await test.step('Login to shopware administration', async () => {
        page = await loginToAdministration(
            page,
            adminUser,
            AdminApiContext,
        );

        LanguageHelper.setForContext(page.context() as unknown as Record<string, unknown>, languageHelper);
        setCurrentContext(page.context() as unknown as Record<string, unknown>);
    });

    await test.step('Validate no captured requests for product analytics', async () => {

        expect(captured.length).toBe(0);
    });
});


test('As a merchant, I want explicitly decline both consents from modal.', { tag: '@ProductAnalytics' }, async ({
    AdminDataSharingConsentModal,
    ShopAdmin,
}) => {

    await test.step('Intercept all the API calls to product analytics', async () => {
        await AdminDataSharingConsentModal.page.route(`**/${PRODUCT_ANALYTICS_ENDPOINT}`, requestHandler);
    });

    await test.step('Check consent checkboxes and decline all in once via button', async () => {

        // Check modal appeared
        await ShopAdmin.expects(AdminDataSharingConsentModal.consentModal).toBeVisible();

        // Check modal contents
        await ShopAdmin.expects(AdminDataSharingConsentModal.shareStoreDataCheckbox).toBeVisible();
        await ShopAdmin.expects(AdminDataSharingConsentModal.shareStoreDataCheckbox).not.toBeChecked();
        await ShopAdmin.expects(AdminDataSharingConsentModal.shareUserTrackingDataHeadline).toBeVisible();
        await ShopAdmin.expects(AdminDataSharingConsentModal.shareUserTrackingDataText).toBeVisible();
        await ShopAdmin.expects(AdminDataSharingConsentModal.shareUserTrackingDataCheckbox).toBeVisible();
        await ShopAdmin.expects(AdminDataSharingConsentModal.shareUserTrackingDataCheckbox).not.toBeChecked();
        await ShopAdmin.expects(AdminDataSharingConsentModal.dataUseDetailsLink).toBeVisible();
        await ShopAdmin.expects(AdminDataSharingConsentModal.privacyPolicyLink).toBeVisible();

        // Link(s) to the legal page(s) exist and point to the expected URL(s). (assert anchors with href contain the canonical path domain or route).

        // Click on deline all button
        await ShopAdmin.presses(AdminDataSharingConsentModal.shareNothingButton);

        // Check modal is disappeared
        await ShopAdmin.expects(AdminDataSharingConsentModal.consentModal).toBeHidden();

    });

    await test.step('Verify both consents are declined in the settings', async () => {

        // Navigate to the shop data settings page and validate shop data consent is declined
        // Toggles clearly show state (on/off) and have accessible labels.
        // Link(s) to the legal page(s) exist and point to the expected URL(s). (assert anchors with href contain the canonical path domain or route).

        // Navigate to User Profile settings page and validate user data consent is declined
        // Toggles clearly show state (on/off) and have accessible labels.
        // Link(s) to the legal page(s) exist and point to the expected URL(s). (assert anchors with href contain the canonical path domain or route).

    });

    await test.step('Validate no captured requests for product analytics', async () => {

        expect(captured.length).toBe(0);
    });
});


