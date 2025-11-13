import { test } from '@fixtures/AcceptanceTest';
import { expect, Request, type Response, Route } from '@playwright/test';

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
}) => {

    let consentResponsePromise: Promise<Response>;
    let response: Response;

    await test.step('Intercept all the API calls to product analytics and entity gateway', async () => {
        await AdminDashboard.page.route(`**/${PRODUCT_ANALYTICS_ENDPOINT}`, requestHandler);
        await AdminDashboard.page.route(`**/${ENTITY_GATEWAY_ENDPOINT}/accept-consent`, requestHandler);
    });

    await test.step('Accept all consent checkboxes in once via button', async () => {

        // Click on accept all consents button
        //await AdminDashboard.page.locator('button[data-testid="data-sharing-consent-accept-all-button"]').click();

        // Validate both consents are accepted
        expect(captured.find(r => r.postData.includes('accept-consent'))).toBeGreaterThan(0);
        expect(captured.find(r => r.postData.includes(`${PRODUCT_ANALYTICS_ENDPOINT}`))).toBeGreaterThan(0);

        // Check modal is disappeared
        await ShopAdmin.expects(AdminDashboard.dataSharingConsentBanner).toBeHidden();

    });

    await test.step('Verify both consents are given.', async () => {

        // Navigate to the shop data settings page and validate shop data consent is given
        await ShopAdmin.goesTo(AdminDataSharing.url());
        // expects shop data consent is given

        // Navigate to User Profile settings page and validate user data consent is given
        await ShopAdmin.goesTo(AdminYourProfile.url());
        // expects user data consent is given

    });
});


