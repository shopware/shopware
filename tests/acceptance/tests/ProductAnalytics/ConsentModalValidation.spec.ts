import { test, expect, Page, AdminPageObjects, createNewAdminPageContext, loginToAdministration, User } from '@fixtures/AcceptanceTest';
import { parseCapturedRequests, removeSymfonyToolbar, setupConsentRevokeInterceptor,
    setupConsentInterceptor, setupProductAnalyticsInterceptor, waitForEventCount,
    CapturedRequest,
} from '@helpers/productanalytics-helpers';
const TRACKING_EVENT_ENDPOINT = 'event';
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

        const { capturedTrackingEventRequests, trackingEventHandler } = setupProductAnalyticsInterceptor();

        const page: Page = await createNewAdminPageContext(browser, SalesChannelBaseConfig);
        const user: User = await TestDataService.createUser({ createdAt: '2024-01-01T00:00:00.000Z' });
        const AdminConsentModal = new AdminPageObjects['DataSharingConsentModal'](page);
        const AdminSettingsListing = new AdminPageObjects['SettingsListing'](page);

        await test.step('Modify product analytics API and consent API requests.', async () => {

            const { consentHandler } = setupConsentInterceptor();
            const { consentRevokeHandler } = setupConsentRevokeInterceptor();

            await page.route(`**/${TRACKING_EVENT_ENDPOINT}**`, trackingEventHandler);
            await page.route(`**/${CONSENTS_ENDPOINT}`, consentHandler);
            await page.route(`**/${CONSENTS_ENDPOINT}/revoke`, consentRevokeHandler);
        });

        await test.step('Login to shopware administration', async () => {

            await loginToAdministration(
                page,
                user,
                TestDataService.AdminApiClient,
            );
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
        });

        await test.step('Validate modal disappeared.', async () => {
            await expect(AdminConsentModal.consentModal).toBeHidden();
        });

        await test.step('Validate anonymous events are fired.', async () => {

            // We expect 4 events to be fired when rejecting consents:
            // 1 consent_modal_viewed (Dashboard)
            // 2 consent_status_change (one for each consent)
            // 1 consent_modal_decision
            const requests = parseCapturedRequests(capturedTrackingEventRequests);
            expect(requests.length).toBeGreaterThanOrEqual(1);

            const getAnalyticsEvents = () =>
                parseCapturedRequests(capturedTrackingEventRequests).flatMap(request => request.events);

            await waitForEventCount(getAnalyticsEvents, 4);

            const events = getAnalyticsEvents();

            const consentModalViewed = events.filter(e => e.name === 'consent_modal_viewed');
            const consentStatusChange = events.filter(e => e.name === 'consent_status_change');
            const consentModalDecision = events.filter(e => e.name === 'consent_modal_decision');

            expect(consentModalViewed).toHaveLength(1);
            expect(consentStatusChange).toHaveLength(2);
            expect(consentModalDecision).toHaveLength(1);

            const consentModalViewedEvents = events.filter(e => e.name === 'consent_modal_viewed');

            expect(consentModalViewedEvents).toHaveLength(1);

            expect(consentModalViewedEvents).toEqual(
                expect.arrayContaining([
                    expect.objectContaining({
                        properties: expect.objectContaining({
                            consents_shown: expect.arrayContaining(['backend_data', 'product_analytics']),
                        }),
                    }),
                ])
            );

            const consentStatusChangeEvents = events.filter(e => e.name === 'consent_status_change');

            expect(consentStatusChangeEvents).toHaveLength(2);

            expect(consentStatusChangeEvents).toEqual(
                expect.arrayContaining([
                    expect.objectContaining({
                        properties: expect.objectContaining({
                            consent: 'backend_data',
                            status: 'declined',
                        }),
                    }),
                    expect.objectContaining({
                        properties: expect.objectContaining({
                            consent: 'product_analytics',
                            status: 'declined',
                        }),
                    }),
                ])
            );

            const consentModalDecisionEvents = events.filter(e => e.name === 'consent_modal_decision');

            expect(consentModalDecisionEvents).toHaveLength(1);

            expect(consentModalDecisionEvents).toEqual(
                expect.arrayContaining([
                    expect.objectContaining({
                        properties: expect.objectContaining({
                            backend_data_changed: false,
                            backend_data_state: 'revoked',
                            product_analytics_changed: false,
                            product_analytics_state: 'revoked',
                        }),
                    }),
                ])
            );
        });

        await test.step('Validate no further captured requests for product analytics after revoke.', async () => {

            // make sure consent modal is not shown
            const { consentHandler } = setupConsentInterceptor({
                backend_data: { status: 'declined' },
                product_analytics: { status: 'declined' },
            });
            await page.route(`**/${CONSENTS_ENDPOINT}`, consentHandler);

            await page.goto(AdminSettingsListing.url());
            await AdminSettingsListing.privacyLink.click();

            const getAnalyticsEvents = () =>
                parseCapturedRequests(capturedTrackingEventRequests).flatMap(request => request.events);

            await waitForEventCount(getAnalyticsEvents, 4);
        });

        await test.step('Validate backend data consent is false in UI by default.', async () => {

            const AdminDataSharing = new AdminPageObjects['DataSharing'](page, InstanceMeta);
            await expect(AdminDataSharing.dataSharingStoreDataCheckbox).not.toBeChecked();
            await expect(AdminDataSharing.dataSharingStoreDataCheckbox).toBeEditable();
        });

        await test.step('Cleanup created user.', async () => {

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

        const page: Page = await createNewAdminPageContext(browser, SalesChannelBaseConfig);
        const user: User = await TestDataService.createUser({ createdAt: '2024-01-01T00:00:00.000Z' });

        await test.step('Modify product analytics API and consent API requests.', async () => {

            const { trackingEventHandler } = setupProductAnalyticsInterceptor();
            const { consentHandler } = setupConsentInterceptor({ backend_data: { status: 'accepted' } });

            await page.route(`**/${TRACKING_EVENT_ENDPOINT}**`, trackingEventHandler);
            await page.route(`**/${CONSENTS_ENDPOINT}`, consentHandler);
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

            await expect(AdminConsentModal.consentModal).toBeVisible();
            await expect(AdminConsentModal.shareStoreDataCheckbox).toHaveCount(0);
            await expect(AdminConsentModal.shareUsageDataCheckbox).toHaveCount(0);
            await expect(AdminConsentModal.shareUsageDataHeadline).toBeVisible()
            await expect(AdminConsentModal.shareUsageDataText).toBeVisible()
            await expect(AdminConsentModal.privacyPolicyLink).toBeVisible();
        });

        await test.step('Cleanup.', async () => {

            await page.close();
        });
    });

test('Only authorized users in administration can change store consent and user data consent', { tag: '@ProductAnalytics' }, async ({
    TestDataService,
    SalesChannelBaseConfig,
    browser,
    InstanceMeta,
}) => {

    const page: Page = await createNewAdminPageContext(browser, SalesChannelBaseConfig);
    const user: User = await TestDataService.createUser({ admin: false, createdAt: '2024-01-01T00:00:00.000Z' });

    await test.step('Modify product analytics API and consent API requests.', async () => {

        const { trackingEventHandler } = setupProductAnalyticsInterceptor();
        const { consentHandler } = setupConsentInterceptor();
        const { consentRevokeHandler } = setupConsentRevokeInterceptor();

        await page.route(`**/${TRACKING_EVENT_ENDPOINT}**`, trackingEventHandler);
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

        const page: Page = await createNewAdminPageContext(browser, SalesChannelBaseConfig);
        const AdminConsentModal = new AdminPageObjects['DataSharingConsentModal'](page);

        await test.step('Modify product analytics API and consent API requests.', async () => {

            const { trackingEventHandler } = setupProductAnalyticsInterceptor();
            const { consentHandler } = setupConsentInterceptor();
            const { consentRevokeHandler } = setupConsentRevokeInterceptor();

            await page.route(`**/${TRACKING_EVENT_ENDPOINT}**`, trackingEventHandler);
            await page.route(`**/${CONSENTS_ENDPOINT}`, consentHandler);
            await page.route(`**/${CONSENTS_ENDPOINT}/revoke`, consentRevokeHandler);
        });

        await test.step('Login to shopware administration with first user', async () => {

            const user1: User = await TestDataService.createUser({ createdAt: '2024-01-01T00:00:00.000Z' });

            await loginToAdministration(
                page,
                user1,
                TestDataService.AdminApiClient,
            );

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

            await page.close();
        });

        await test.step('Cleanup.', async () => {

            await page.close();
        });
    });
