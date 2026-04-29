import { test, expect, Page, AdminPageObjects, createNewAdminPageContext, loginToAdministration, User } from '@fixtures/AcceptanceTest';
import { parseCapturedRequests, removeSymfonyToolbar,
    setupConsentInterceptor, setupProductAnalyticsInterceptor, waitForEventCount,
} from '@helpers/productanalytics-helpers';
import { satisfies } from 'compare-versions';

const TRACKING_EVENT_ENDPOINT = 'event';
const CONSENTS_ENDPOINT = 'consents';
const MONTHS_AHEAD = 3;

test.describe('Product Analytics - Consent Modal Validation',
    { tag: '@ProductAnalytics' }, () => {
    test(
        'As a merchant, opening the Product Analytics consent modal, should send anonymous events.',
        { tag: '@ProductAnalytics' },
        async ({
            SalesChannelBaseConfig,
            browser,
            TestDataService,
            InstanceMeta,
        }) => {

            test.skip(satisfies(InstanceMeta.version, '<6.7.9.0'), 'Data sharing consent modal only available since version 6.7.9.0');

            const { capturedTrackingEventRequests, trackingEventHandler } = setupProductAnalyticsInterceptor();

            const page: Page = await createNewAdminPageContext(browser, SalesChannelBaseConfig);
            const user: User = await TestDataService.createUser();
            const AdminConsentModal = new AdminPageObjects['DataSharingConsentModal'](page);
            const AdminSettingsListing = new AdminPageObjects['SettingsListing'](page);

            await page.clock.install({ time: new Date(new Date().setMonth(new Date().getMonth() + MONTHS_AHEAD)) });

            await test.step('Modify product analytics API and consent API requests.', async () => {

                const { consentHandler } = setupConsentInterceptor();

                await page.route(`**/${TRACKING_EVENT_ENDPOINT}**`, trackingEventHandler);
                await page.route(`**/${CONSENTS_ENDPOINT}`, consentHandler);
                await page.route(`**/${CONSENTS_ENDPOINT}/revoke`, consentHandler);
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

                // Cumulative expected events: 1 consent_modal_viewed + 2 consent_status_change + 1 consent_modal_decision = 4.
                const requests = parseCapturedRequests(capturedTrackingEventRequests);
                expect(requests.length).toBeGreaterThanOrEqual(1);

                const getAnalyticsEvents = () =>
                    parseCapturedRequests(capturedTrackingEventRequests).flatMap(request => request.events);

                await waitForEventCount(getAnalyticsEvents, 4);

                const events = getAnalyticsEvents();

                const consentModalViewedEvents = events.filter(e => e.name === 'consent_modal_viewed');
                const consentStatusChangeEvents = events.filter(e => e.name === 'consent_status_change');
                const consentModalDecisionEvents = events.filter(e => e.name === 'consent_modal_decision');

                expect(consentModalViewedEvents).toHaveLength(1);
                expect(consentStatusChangeEvents).toHaveLength(2);
                expect(consentModalDecisionEvents).toHaveLength(1);

                expect(consentModalViewedEvents).toEqual(
                    expect.arrayContaining([
                        expect.objectContaining({
                            properties: expect.objectContaining({
                                consents_shown: expect.arrayContaining(['backend_data', 'product_analytics']),
                            }),
                        }),
                    ])
                );

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
                    backend_data: 'declined',
                    product_analytics: 'declined',
                });
                await page.route(`**/${CONSENTS_ENDPOINT}`, consentHandler);

                await page.goto(AdminSettingsListing.url());
                await AdminSettingsListing.privacyLink.click();

                const getAnalyticsEvents = () =>
                    parseCapturedRequests(capturedTrackingEventRequests).flatMap(request => request.events);

                // Cumulative expected events stay at 4 (0 additional events after opening privacy settings).
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
            InstanceMeta,
               }) => {

            test.skip(satisfies(InstanceMeta.version, '<6.7.9.0'), 'Data sharing consent modal only available since version 6.7.9.0');

            const page: Page = await createNewAdminPageContext(browser, SalesChannelBaseConfig);
            const user: User = await TestDataService.createUser();

            await page.clock.install({ time: new Date(new Date().setMonth(new Date().getMonth() + MONTHS_AHEAD)) });

            await test.step('Modify product analytics API and consent API requests.', async () => {

                const { trackingEventHandler } = setupProductAnalyticsInterceptor();
                const { consentHandler } = setupConsentInterceptor({ backend_data: 'accepted' });

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

        test.skip(satisfies(InstanceMeta.version, '<6.7.9.0'), 'Data sharing consent modal only available since version 6.7.9.0');

        const page: Page = await createNewAdminPageContext(browser, SalesChannelBaseConfig);
        const user: User = await TestDataService.createUser({ admin: false });

        await page.clock.install({ time: new Date(new Date().setMonth(new Date().getMonth() + MONTHS_AHEAD)) });

        await test.step('Modify product analytics API and consent API requests.', async () => {

            const { trackingEventHandler } = setupProductAnalyticsInterceptor();
            const { consentHandler } = setupConsentInterceptor();

            await page.route(`**/${TRACKING_EVENT_ENDPOINT}**`, trackingEventHandler);
            await page.route(`**/${CONSENTS_ENDPOINT}`, consentHandler);
            await page.route(`**/${CONSENTS_ENDPOINT}/revoke`, consentHandler);
        });

        await test.step('Setup user which can not change store consent but user data consent', async () => {

           const permissions = [
                    'language:read',
                    'locale:read',
                    'log_entry:create',
                    'message_queue_stats:read',
                    'system_config:read',
                    'user.update_profile', 'user:read', 'user_change_me', 'user_config:create', 'user_config:read', 'user_config:update',
                ];

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

            await expect(AdminConsentModal.consentModal).toBeVisible({ timeout: 10_000 });
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
            InstanceMeta,
               }) => {

            test.skip(satisfies(InstanceMeta.version, '<6.7.9.0'), 'Data sharing consent modal only available since version 6.7.9.0');

            const page: Page = await createNewAdminPageContext(browser, SalesChannelBaseConfig);
            const AdminConsentModal = new AdminPageObjects['DataSharingConsentModal'](page);

            await page.clock.install({ time: new Date(new Date().setMonth(new Date().getMonth() + MONTHS_AHEAD)) });

            await test.step('Modify product analytics API and consent API requests.', async () => {

                const { trackingEventHandler } = setupProductAnalyticsInterceptor();
                const { consentHandler } = setupConsentInterceptor();

                await page.route(`**/${TRACKING_EVENT_ENDPOINT}**`, trackingEventHandler);
                await page.route(`**/${CONSENTS_ENDPOINT}`, consentHandler);
                await page.route(`**/${CONSENTS_ENDPOINT}/accept`, consentHandler);
                await page.route(`**/${CONSENTS_ENDPOINT}/revoke`, consentHandler);
            });

            await test.step('Login to shopware administration with first user', async () => {

                const user1: User = await TestDataService.createUser();

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
                const user2: User = await TestDataService.createUser();

                await page.clock.install({ time: new Date(new Date().setMonth(new Date().getMonth() + MONTHS_AHEAD)) });

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
});
