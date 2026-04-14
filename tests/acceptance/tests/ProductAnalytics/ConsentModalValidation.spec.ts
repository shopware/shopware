import { test, expect, Page, Locator } from '@fixtures/AcceptanceTest';
import { parseCapturedRequests, removeSymfonyToolbar, setupConsentRevokeInterceptor,
    setupConsentInterceptor, setupProductAnalyticsInterceptor, waitForCapturedRequests } from '@helpers/productanalytics-helpers';
import {AdminPageObjects, createNewAdminPageContext, loginToAdministration, User } from '@shopware-ag/acceptance-test-suite';

const PRODUCT_ANALYTICS_ENDPOINT = 'event';
const CONSENTS_ENDPOINT = 'consents';

test(
    'As a merchant, opening the Product Analytics consent modal, should send anonymous events.',
    { tag: '@ProductAnalytics' },
    async ({
        SalesChannelBaseConfig,
        browser,
        TestDataService,
        InstanceMeta,
    }) => {

        const { capturedRequests, handler } = setupProductAnalyticsInterceptor();
        const { consentHandler } = setupConsentInterceptor();
        const { consentRevokeHandler } = setupConsentRevokeInterceptor();

        const page: Page = await createNewAdminPageContext(browser, SalesChannelBaseConfig);
        const user: User = await TestDataService.createUser({ createdAt: '2024-01-01T00:00:00.000Z' });
        const AdminConsentModal = new AdminPageObjects['DataSharingConsentModal'](page);
        const AdminSettingsListing = new AdminPageObjects['SettingsListing'](page);

        await test.step('Modify product analytics API and consent API requests.', async () => {

            await page.route(`**/${PRODUCT_ANALYTICS_ENDPOINT}**`, handler);
            await page.route(`**/${CONSENTS_ENDPOINT}`, consentHandler);
            await page.route(`**/${CONSENTS_ENDPOINT}/revoke`, consentRevokeHandler);

        });

        await test.step('Login to shopware administration', async () => {

            await loginToAdministration(
                page,
                user,
                TestDataService.AdminApiClient,
            );
            await page.goto(AdminSettingsListing.url());

            await waitForCapturedRequests(capturedRequests, 2);
        });

        await test.step('Validate modal appeared.', async () => {

            await expect(AdminConsentModal.consentModal).toBeVisible();
            await expect(AdminConsentModal.shareStoreDataCheckbox).not.toBeChecked();
            await expect(AdminConsentModal.shareStoreDataHeadline).toBeVisible();
            await expect(AdminConsentModal.shareStoreDataText).toBeVisible();
            await expect(AdminConsentModal.shareUsageDataHeadline).toBeVisible();
            await expect(AdminConsentModal.shareUsageDataText).toBeVisible();
            await expect(AdminConsentModal.shareUsageDataCheckbox).toBeVisible();
            await expect(AdminConsentModal.shareUsageDataCheckbox).not.toBeChecked();
            await expect(AdminConsentModal.storeDataCollectionDetailsLink).toBeVisible();
            await expect(AdminConsentModal.privacyPolicyLink).toBeVisible();
            await expect(AdminConsentModal.allowAllButton).toBeVisible();
            await expect(AdminConsentModal.rejectAllButton).toBeVisible();
        });

        await test.step('Reject all consents.', async () => {

            await removeSymfonyToolbar(page);
            await AdminConsentModal.rejectAllButton.click();
            await waitForCapturedRequests(capturedRequests, 5);
        });

        await test.step('Validate modal disappeared.', async () => {
            await expect(AdminConsentModal.consentModal).toBeHidden();
        });

        await test.step('Validate anonymous events are fired.', async () => {

            const requests = parseCapturedRequests(capturedRequests);
            expect(requests).toHaveLength(5);

            const events = requests.flatMap((request) => request.events);
            expect(events).toHaveLength(5);

            const eventTypes = events.map(e => e.name);
            expect(eventTypes).toEqual([
                'consent_modal_viewed',
                'consent_modal_viewed',
                'consent_status_change',
                'consent_status_change',
                'consent_modal_decision',
            ]);

            const [
                consentModalViewed1,
                consentModalViewed2,
                consentStatusChange1,
                consentStatusChange2,
                consentModalDecision,
            ] = events;

            const consentModalViewed1Props = consentModalViewed1.properties;
            expect(consentModalViewed1Props.consents_shown).toEqual(
                expect.arrayContaining(['backend_data', 'product_analytics'])
            );

            const consentModalViewed2Props = consentModalViewed2.properties;
            expect(consentModalViewed2Props.consents_shown).toEqual(
                expect.arrayContaining(['backend_data', 'product_analytics'])
            );

            const consentStatusChange1Props = consentStatusChange1.properties;
            expect(consentStatusChange1Props.consent).toBe('backend_data');
            expect(consentStatusChange1Props.status).toBe('declined');

            const consentStatusChange2Props = consentStatusChange2.properties;
            expect(consentStatusChange2Props.consent).toBe('product_analytics');
            expect(consentStatusChange2Props.status).toBe('declined');

            const consentModalDecisionProps = consentModalDecision.properties;
            expect(consentModalDecisionProps.backend_data_changed).toBe(false);
            expect(consentModalDecisionProps.backend_data_state).toBe('revoked');
            expect(consentModalDecisionProps.product_analytics_changed).toBe(false);
            expect(consentModalDecisionProps.product_analytics_state).toBe('revoked');
        });

        await test.step('Validate no captured requests for product analytics after revoke.', async () => {

            await AdminSettingsListing.privacyLink.click();
            // no new events should be fired on page navigation after consents are revoked
            await waitForCapturedRequests(capturedRequests, 5);
        });

        await test.step('Validate backend data consent is false in UI by default.', async () => {

            const AdminDataSharing = new AdminPageObjects['DataSharing'](page, InstanceMeta);
            await expect(AdminDataSharing.dataSharingStoreDataCheckbox).not.toBeChecked();
            await expect(AdminDataSharing.dataSharingStoreDataCheckbox).toBeEditable();
        });

        await test.step('Cleanup created user.', async () => {

            TestDataService.addCreatedRecord('user', user.id);
            await page.close();
        });
});

test(
    'Existing backend-data consent is checked before rendering consent modal',
    { tag: '@ProductAnalytics' },
    async ({
               SalesChannelBaseConfig,
               browser,
               TestDataService,
           }) => {

        const { capturedRequests, handler } = setupProductAnalyticsInterceptor();
        const { consentHandler } = setupConsentInterceptor({ backend_data: { status: 'accepted' } });

        const page: Page = await createNewAdminPageContext(browser, SalesChannelBaseConfig);
        const user: User = await TestDataService.createUser({ createdAt: '2024-01-01T00:00:00.000Z' });

        await test.step('Modify product analytics API and consent API requests.', async () => {

            await page.route(`**/${PRODUCT_ANALYTICS_ENDPOINT}**`, handler);
            await page.route(`**/${CONSENTS_ENDPOINT}`, consentHandler);
        });

        await test.step('Login to shopware administration', async () => {

            await loginToAdministration(
                page,
                user,
                TestDataService.AdminApiClient,
            );

            await waitForCapturedRequests(capturedRequests, 1);
        });

        await test.step('Validate no store data consent option available.', async () => {

            const AdminConsentModal = new AdminPageObjects['DataSharingConsentModal'](page);

            await expect(AdminConsentModal.consentModal).toBeVisible();
            await expect(AdminConsentModal.shareStoreDataCheckbox).toHaveCount(0);
            await expect(AdminConsentModal.shareUsageDataCheckbox).toHaveCount(0);
            await expect(AdminConsentModal.shareUsageDataHeadline).toBeVisible()
            await expect(AdminConsentModal.shareUsageDataText).toBeVisible()
            await expect(AdminConsentModal.privacyPolicyLink).toBeVisible();
        });

        await test.step('Cleanup.', async () => {

            TestDataService.addCreatedRecord('user', user.id);
            await page.close();
        });
    });

test('Only authorized users in administration can change store consent and user data consent', { tag: '@ProductAnalytics' }, async ({
    TestDataService,
    SalesChannelBaseConfig,
    browser,
    InstanceMeta,
}) => {

    const { handler } = setupProductAnalyticsInterceptor();
    const { consentHandler } = setupConsentInterceptor();
    const { consentRevokeHandler } = setupConsentRevokeInterceptor();

    const page: Page = await createNewAdminPageContext(browser, SalesChannelBaseConfig);
    const user: User = await TestDataService.createUser({ admin: false, createdAt: '2024-01-01T00:00:00.000Z' });

    await test.step('Modify product analytics API and consent API requests.', async () => {

        await page.route(`**/${PRODUCT_ANALYTICS_ENDPOINT}**`, handler);
        await page.route(`**/${CONSENTS_ENDPOINT}`, consentHandler);
        await page.route(`**/${CONSENTS_ENDPOINT}/revoke`, consentRevokeHandler);
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
                'user.update_profile', 'user:read', 'user_change_me', 'user_config:create', 'user_config:read', 'user_config:update',

            ];
        } else {
            permissions = [
                'language:read',
                'locale:read',
                'log_entry:create',
                'message_queue_stats:read',
                'system_config:read',
                'user.update_profile', 'user:read', 'user_change_me', 'user_config:create', 'user_config:read', 'user_config:update',
            ];
        }

        const onlyChangeUserProfilePermissions = await TestDataService.createAclRole({ privileges: permissions });
        await TestDataService.assignAclRoleUser(onlyChangeUserProfilePermissions.id, user.id);
    });

    await test.step('Login to shopware administration', async () => {

        await loginToAdministration(
            page,
            user,
            TestDataService.AdminApiClient,
        );
    });

    await test.step('Validate no store data consent option available.', async () => {

        const AdminConsentModal = new AdminPageObjects['DataSharingConsentModal'](page);
        const AdminYourProfile = new AdminPageObjects['YourProfile'](page);

        await AdminYourProfile.page.goto(AdminYourProfile.url('privacy-preferences'));

        await expect(AdminConsentModal.consentModal).toBeVisible();
        await expect(AdminConsentModal.shareStoreDataCheckbox).toHaveCount(0);
        await expect(AdminConsentModal.shareUsageDataCheckbox).toHaveCount(0);
        await expect(AdminConsentModal.shareUsageDataHeadline).toBeVisible()
        await expect(AdminConsentModal.shareUsageDataText).toBeVisible()
        await expect(AdminConsentModal.privacyPolicyLink).toBeVisible();

        await AdminConsentModal.page.getByRole('button', { name: 'Decline' }).click();

        await expect(AdminYourProfile.dataSharingUsageDataCheckbox).toBeEditable();
        await expect(AdminYourProfile.dataSharingUsageDataCheckbox).not.toBeChecked();
    });

    await test.step('Cleanup.', async () => {

        TestDataService.addCreatedRecord('user', user.id);
        await page.close();
    });
});

test(
    'Each user can only manage their own user-data consent.',
    { tag: '@ProductAnalytics' },
    async ({
               SalesChannelBaseConfig,
               browser,
               TestDataService,
           }) => {

        const { capturedRequests, handler } = setupProductAnalyticsInterceptor();
        const { consentHandler } = setupConsentInterceptor();
        const { consentRevokeHandler } = setupConsentRevokeInterceptor();

        const page: Page = await createNewAdminPageContext(browser, SalesChannelBaseConfig);
        const user1: User = await TestDataService.createUser({ createdAt: '2024-01-01T00:00:00.000Z' });
        const AdminConsentModal = new AdminPageObjects['DataSharingConsentModal'](page);

        await test.step('Modify product analytics API and consent API requests.', async () => {

            await page.route(`**/${PRODUCT_ANALYTICS_ENDPOINT}**`, handler);
            await page.route(`**/${CONSENTS_ENDPOINT}`, consentHandler);
            await page.route(`**/${CONSENTS_ENDPOINT}/revoke`, consentRevokeHandler);
        });

        await test.step('Login to shopware administration with first user', async () => {

            await loginToAdministration(
                page,
                user1,
                TestDataService.AdminApiClient,
            );

            await waitForCapturedRequests(capturedRequests, 1);

            await removeSymfonyToolbar(page);
            await AdminConsentModal.shareUsageDataCheckbox.click();
            await AdminConsentModal.savePreferencesButton.click();
        });

        await test.step('Validate second user sees their own consent.', async () => {

            const page = await createNewAdminPageContext(browser, SalesChannelBaseConfig);
            const AdminConsentModal = new AdminPageObjects['DataSharingConsentModal'](page);
            const user2: User = await TestDataService.createUser({ createdAt: '2024-01-01T00:00:00.000Z' });

            await loginToAdministration(
                page,
                user2,
                TestDataService.AdminApiClient,
            );

            await removeSymfonyToolbar(page);
            await AdminConsentModal.shareUsageDataCheckbox.scrollIntoViewIfNeeded();
            await expect(AdminConsentModal.shareUsageDataCheckbox).not.toBeChecked();
            await expect(AdminConsentModal.shareUsageDataCheckbox).toBeEditable();

            TestDataService.addCreatedRecord('user', user2.id);
            await page.close();

        });

        await test.step('Cleanup.', async () => {

            TestDataService.addCreatedRecord('user', user1.id);
            await page.close();
        });
    });
