import { test } from '@fixtures/AcceptanceTest';
import { expect, Request, Route } from '@playwright/test';

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

/** Endpoint for Product Analytics Gateway API.
 */
const ENTITY_GATEWAY_ENDPOINT = 'usage-data';

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

test('As a merchant, I want explicitly accept both consents from modal.', { tag: '@ProductAnalytics' }, async ({
    AdminDashboard,
    ShopAdmin,
    AdminYourProfile,
    AdminDataSharing,
    AdminDataSharingConsentModal,
}) => {


    await test.step('Intercept all the API calls to product analytics and entity gateway', async () => {
        await AdminDashboard.page.route(`**/${PRODUCT_ANALYTICS_ENDPOINT}`, requestHandler);
        await AdminDashboard.page.route(`**/${ENTITY_GATEWAY_ENDPOINT}/accept-consent`, requestHandler);
    });

    await test.step('Accept all consent checkboxes in once via button', async () => {

        // Check modal appeared
        await ShopAdmin.expects(AdminDataSharingConsentModal.consentModal).toBeVisible();

        // Check modal contents
        await ShopAdmin.expects(AdminDataSharingConsentModal.shareStoreDataHeadline).toBeVisible();
        await ShopAdmin.expects(AdminDataSharingConsentModal.shareStoreDataText).toBeVisible();
        await ShopAdmin.expects(AdminDataSharingConsentModal.shareStoreDataCheckbox).toBeVisible();
        await ShopAdmin.expects(AdminDataSharingConsentModal.shareStoreDataCheckbox).not.toBeChecked();
        await ShopAdmin.expects(AdminDataSharingConsentModal.shareUserTrackingDataHeadline).toBeVisible();
        await ShopAdmin.expects(AdminDataSharingConsentModal.shareUserTrackingDataText).toBeVisible();
        await ShopAdmin.expects(AdminDataSharingConsentModal.shareUserTrackingDataCheckbox).toBeVisible();
        await ShopAdmin.expects(AdminDataSharingConsentModal.shareUserTrackingDataCheckbox).not.toBeChecked();
        await ShopAdmin.expects(AdminDataSharingConsentModal.dataUseDetailsLink).toBeVisible();
        await ShopAdmin.expects(AdminDataSharingConsentModal.privacyPolicyLink).toBeVisible();

        // Click on accept all consents button
        await ShopAdmin.presses(AdminDataSharingConsentModal.shareAllButton);

        // Validate both consents are accepted
        expect(captured.find(r => r.postData.includes(`accept-consent`))).toBeGreaterThan(0);
        expect(captured.find(r => r.postData.includes(`${PRODUCT_ANALYTICS_ENDPOINT}`))).toBeGreaterThan(0);

        // Check modal is disappeared
        await ShopAdmin.expects(AdminDataSharingConsentModal.consentModal).toBeHidden();

    });

    await test.step('Verify both consents are given.', async () => {

        // Navigate to the shop data settings page and validate shop data consent is given
        await ShopAdmin.goesTo(AdminDataSharing.url());
        await ShopAdmin.expects(AdminDataSharing.dataSharingStoreDataCheckbox).toBeChecked();
        await ShopAdmin.expects(AdminDataSharing.dataSharingCardTitle).toBeVisible();
        await ShopAdmin.expects(AdminDataSharing.dataUseDetailsLink).toBeVisible();
        await ShopAdmin.expects(AdminDataSharing.privacyPolicyLink).toBeVisible();

        // Navigate to User Profile settings page and validate user data consent is given
        await ShopAdmin.goesTo(AdminYourProfile.url('privacy-preferences'));
        await ShopAdmin.expects(AdminYourProfile.dataSharingMyDataCheckbox).toBeChecked();
        await ShopAdmin.expects(AdminYourProfile.dataSharingCardTitle).toBeVisible();
        await ShopAdmin.expects(AdminYourProfile.dataUseDetailsLink).toBeVisible();
        await ShopAdmin.expects(AdminYourProfile.privacyPolicyLink).toBeVisible();

    });
});


