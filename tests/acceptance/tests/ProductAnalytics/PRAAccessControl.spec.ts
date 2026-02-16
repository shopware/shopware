import { test } from '@fixtures/AcceptanceTest';
import { expect, Request, Route, Page } from '@playwright/test';
import {
    createNewAdminPageContext,
    loginToAdministration,
    User,
} from '@fixtures/AcceptanceTest';
import { AdminPageObjects } from '@shopware-ag/acceptance-test-suite';

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

test('Only authorized users in administration can change store consent.', { tag: '@ProductAnalytics' }, async ({
    TestDataService,
    SalesChannelBaseConfig,
    browser,
    InstanceMeta,
}) => {



    let page: Page;
    let customUser: User;
    let AdminYourProfile;
    let AdminDataSharingConsentModal;

    await test.step('Setup user which can not change store consent but user data consent', async () => {

        let permissions: string[];
        // eslint-disable-next-line playwright/no-conditional-in-test
        if (InstanceMeta.isSaaS) {
            permissions = [
                'language:read',
                'locale:read',
                'log_entry:create',
                'message_queue_stats:read',
                'system_config:read',
        ];
        } else {
            permissions = [
                'language:read',
                'locale:read',
                'log_entry:create',
                'message_queue_stats:read',
                'system_config:read',
            ];
        }

        customUser = await TestDataService.createUser();
        const onlyChangeUserProfilePermissions = await TestDataService.createAclRole({ privileges: permissions });
        await TestDataService.assignAclRoleUser(onlyChangeUserProfilePermissions.id, customUser.id);
    });

    await test.step('Setup page object before login to shopware administration', async () => {

        page = await createNewAdminPageContext(browser, SalesChannelBaseConfig);
    });

    await test.step('Intercept all the API calls to product analytics', async () => {
        await page.route(`**/${PRODUCT_ANALYTICS_ENDPOINT}`, requestHandler);
    });

    await test.step('Login to shopware administration', async () => {
        await loginToAdministration(
            page,
            customUser,
            TestDataService.AdminApiClient,
        );

        AdminYourProfile = new AdminPageObjects['YourProfile'](page);
        AdminDataSharingConsentModal = new AdminPageObjects['DataSharingConsentModal'](page);

        await page.addStyleTag({
            content: `
                    .sf-toolbar {
                        width: 0 !important;
                        height: 0 !important;
                        display: none !important;
                        pointer-events: none !important;
                    }
                    `.trim(),
        });

    });

    await test.step('Validate no captured requests for product analytics', async () => {

        expect(captured.length).toBe(0);
    });

    await test.step('Validate only my data consent can be adjusted in modal', async () => {

        await expect(AdminDataSharingConsentModal.consentModal).toBeVisible();
        await expect(AdminDataSharingConsentModal.shareStoreDataCheckbox).toBeHidden();
        await expect(AdminDataSharingConsentModal.shareUserTrackingDataCheckbox).toBeVisible();
        await expect(AdminDataSharingConsentModal.shareUserTrackingDataCheckbox).toBeEditable();
        await expect(AdminDataSharingConsentModal.shareUserTrackingDataCheckbox).not.toBeChecked();

        await AdminDataSharingConsentModal.savePreferencesButton.click();
    });

    await test.step('Validate privacy preferences of user can be accessed', async () => {

        await page.goto(AdminYourProfile.url('privacy-preferences'));
        await page.addStyleTag({
            content: `
                    .sf-toolbar {
                        width: 0 !important;
                        height: 0 !important;
                        display: none !important;
                        pointer-events: none !important;
                    }
                    `.trim(),
        });

        await expect(AdminYourProfile.dataSharingMyDataCheckbox).toBeVisible();
        await expect(AdminYourProfile.dataSharingMyDataCheckbox).toBeEditable();
        await expect(AdminYourProfile.dataSharingMyDataCheckbox).not.toBeChecked();
    });
});
