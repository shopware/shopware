import { test,
    AdminPageObjects,
    createNewAdminPageContext,
    loginToAdministration,
    User,
} from '@fixtures/AcceptanceTest';
import { expect, Page } from '@playwright/test';
import { removeSymfonyToolbar } from '../../helpers/styleTag-helper';

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

test('Only authorized users in administration can change store consent and user data consent', { tag: '@ProductAnalytics' }, async ({
    TestDataService,
    SalesChannelBaseConfig,
    browser,
    InstanceMeta,
}) => {

    let page: Page;
    let customUser: User;
    let AdminYourProfile;
    let AdminDataSharingConsentModal;

    await test.step('Create user with user data consent given.', async () => {

        //To-Do: Create user with user data consent given via API once endpoint is available. For now, we need to set consent via UI.
    });

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

    await test.step('Login to shopware administration', async () => {

        await loginToAdministration(
            page,
            customUser,
            TestDataService.AdminApiClient,
        );

        AdminYourProfile = new AdminPageObjects['YourProfile'](page);
        AdminDataSharingConsentModal = new AdminPageObjects['DataSharingConsentModal'](page);
        await removeSymfonyToolbar(page);
    });

    await test.step('Validate only my data consent can be adjusted in modal', async () => {

        await expect(AdminDataSharingConsentModal.consentModal).toBeVisible();
        await expect(AdminDataSharingConsentModal.shareStoreDataCheckbox).toBeHidden();
        await expect(AdminDataSharingConsentModal.shareUserTrackingDataCheckbox).toBeVisible();
        await expect(AdminDataSharingConsentModal.shareUserTrackingDataCheckbox).toBeEditable();
        await expect(AdminDataSharingConsentModal.shareUserTrackingDataCheckbox).not.toBeChecked();

        const requestPromise = page.waitForRequest(`**/${PRODUCT_ANALYTICS_ENDPOINT}`, { timeout: 3000 });
        await AdminDataSharingConsentModal.savePreferencesButton.click();
        await expect(requestPromise).rejects.toThrow();
    });

    await test.step('Validate privacy preferences of user can be accessed', async () => {

        await page.goto(AdminYourProfile.url('privacy-preferences'));
        await removeSymfonyToolbar(page);

        await expect(AdminYourProfile.dataSharingMyDataCheckbox).toBeVisible();
        await expect(AdminYourProfile.dataSharingMyDataCheckbox).toBeEditable();
        await expect(AdminYourProfile.dataSharingMyDataCheckbox).not.toBeChecked();
    });
});
