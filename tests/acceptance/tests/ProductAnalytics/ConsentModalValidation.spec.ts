import { test, expect, Page, Locator } from '@fixtures/AcceptanceTest';
import { parseCapturedRequests, removeSymfonyToolbar, setupConsentRevokeInterceptor,
    setupConsentInterceptor, setupProductAnalyticsInterceptor, waitForCapturedRequests } from '@helpers/productanalytics-helpers';
import { createNewAdminPageContext, loginToAdministration, User } from '@shopware-ag/acceptance-test-suite';

const PRODUCT_ANALYTICS_ENDPOINT = 'event';
const CONSENTS_ENDPOINT = 'consents';

test(
    'As a merchant, opening the Product Analytics consent modal, should send anonymous events.',
    { tag: '@ProductAnalytics' },
    async ({
        IdProvider,
        SalesChannelBaseConfig,
        AdminApiContext,
        browser,
        TestDataService,
    }) => {

        const { capturedRequests, handler } = setupProductAnalyticsInterceptor();
        const { consentHandler } = setupConsentInterceptor();
        const { consentRevokeHandler } = setupConsentRevokeInterceptor();

        let page: Page;
        let adminUser: User;
        let consentModal: Locator;
        let rejectAllButton: Locator;

        await test.step('Setup page object before login to shopware administration', async () => {

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
                createdAt: '2024-01-01T00:00:00.000Z',
            };

            const response = await AdminApiContext.post('user', {
                data: adminUser,
            });

            expect(response.ok()).toBeTruthy();
            page = await createNewAdminPageContext(browser, SalesChannelBaseConfig);
        });

        await test.step('Modify product analytics API and consent API requests.', async () => {

            await page.route(`**/${PRODUCT_ANALYTICS_ENDPOINT}**`, handler);
            await page.route(`**/${CONSENTS_ENDPOINT}`, consentHandler);
            await page.route(`**/${CONSENTS_ENDPOINT}/revoke`, consentRevokeHandler);

        });

        await test.step('Login to shopware administration', async () => {
            page = await loginToAdministration(
                page,
                adminUser,
                AdminApiContext,
            );

            await waitForCapturedRequests(capturedRequests, 1);
        });

        await test.step('Validate modal appeared.', async () => {
            consentModal = page.getByRole('dialog').filter({ has: page.getByRole('heading', { name: 'Help us to improve Shopware' }) });
            const shareStoreDataHeadline = consentModal.getByRole('heading', { name: 'Store data' });
            const shareStoreDataText = consentModal.getByText(
                'Anonymous data from your Shopware environment such as orders, diagnostic data, and store data helps us to improve features. You can find an overview of all collected data and details of the agreement here.'
            );
            const shareStoreDataCheckbox = consentModal.getByRole('checkbox', { name: 'Share store data (anonymous)' });
            const shareUsageDataHeadline = consentModal.getByRole('heading', { name: 'Usage data' });
            const shareUsageDataText = consentModal.getByText(
                'We use personal usage data about how you interact with the administration to continously improve usability. You can find all details in our Privacy Policy.'
            );
            const shareUsageDataCheckbox = consentModal.getByRole('checkbox', { name: 'Share Usage data' });
            const storeDataCollectionDetailsLink = consentModal.getByRole('link', { name: 'here' });
            const privacyPolicyLink = consentModal.getByRole('link', { name: 'Privacy Policy' });
            const allowAllButton = consentModal.getByRole('button', { name: 'Allow all' });
            rejectAllButton = consentModal.getByRole('button', { name: 'Reject All' });

            await expect(consentModal).toBeVisible();
            await expect(shareStoreDataCheckbox).not.toBeChecked();
            await expect(shareStoreDataHeadline).toBeVisible();
            await expect(shareStoreDataText).toBeVisible();
            await expect(shareUsageDataHeadline).toBeVisible();
            await expect(shareUsageDataText).toBeVisible();
            await expect(shareUsageDataCheckbox).toBeVisible();
            await expect(shareUsageDataCheckbox).not.toBeChecked();
            await expect(storeDataCollectionDetailsLink).toBeVisible();
            await expect(privacyPolicyLink).toBeVisible();
            await expect(allowAllButton).toBeVisible();
            await expect(rejectAllButton).toBeVisible();
        });

        await test.step('Reject all consents.', async () => {

            await removeSymfonyToolbar(page);
            await rejectAllButton.click();
            await waitForCapturedRequests(capturedRequests, 4);
        });

        await test.step('Validate modal disappeared.', async () => {
            await expect(consentModal).toBeHidden();
        });

        await test.step('Validate anonymous events are fired.', async () => {

            const requests = parseCapturedRequests(capturedRequests);
            expect(requests).toHaveLength(4);

            const events = requests.flatMap((request) => request.events);
            expect(events).toHaveLength(4);

            const eventTypes = events.map(e => e.name);
            expect(eventTypes).toEqual([
                'consent_modal_viewed',
                'consent_status_change',
                'consent_status_change',
                'consent_modal_decision',
            ]);

            const [
                consentModalViewed,
                consentStatusChange1,
                consentStatusChange2,
                consentModalDecision,
            ] = events;

            const consentModalViewedProps = consentModalViewed.properties;
            expect(consentModalViewedProps.consents_shown).toEqual(
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

            await page.getByRole('link', { name: 'Settings' }).click();
            await page.getByRole('link', { name: 'Privacy' }).click();
            // no new events should be fired on page navigation after consents are revoked
            await waitForCapturedRequests(capturedRequests, 4);
        });

        await test.step('Validate backend data consent is false in UI by default.', async () => {

            await expect(page.getByRole('checkbox', { name: 'Share store data (anonymous)' })).not.toBeChecked();

        });

        await test.step('Cleanup created user.', async () => {

            TestDataService.addCreatedRecord('user', adminUser.id);
        });
});

test(
    'Existing backend-data consent is checked before rendering consent modal',
    { tag: '@ProductAnalytics' },
    async ({
               IdProvider,
               SalesChannelBaseConfig,
               browser,
               TestDataService,
           }) => {

        const { capturedRequests, handler } = setupProductAnalyticsInterceptor();
        const { consentHandler } = setupConsentInterceptor({ backend_data: { status: 'accepted' } });

        let page: Page;
        let user: User;
        let consentModal: Locator;

        await test.step('Setup page object before login to shopware administration', async () => {

            const { id, uuid } = IdProvider.getIdPair();

            user = {
                id: uuid,
                username: `admin_${id}`,
                firstName: `${id} admin`,
                lastName: `${id} admin`,
                localeId: SalesChannelBaseConfig.currentLocaleId,
                email: `admin_${id}@example.com`,
                timezone: 'Europe/Berlin',
                password: 'shopware',
                admin: true,
                createdAt: '2024-01-01T00:00:00.000Z',
            };

            const userResponse = await TestDataService.AdminApiClient.post('user', { data: user });
            expect(userResponse.ok()).toBeTruthy();

            page = await createNewAdminPageContext(browser, SalesChannelBaseConfig);
        });

        await test.step('Modify product analytics API and consent API requests.', async () => {

            await page.route(`**/${PRODUCT_ANALYTICS_ENDPOINT}**`, handler);
            await page.route(`**/${CONSENTS_ENDPOINT}`, consentHandler);
        });

        await test.step('Login to shopware administration', async () => {
            page = await loginToAdministration(
                page,
                user,
                TestDataService.AdminApiClient,
            );

            await waitForCapturedRequests(capturedRequests, 1);
        });

        await test.step('Validate no store data consent option available.', async () => {

            consentModal = page.getByRole('dialog').filter({ has: page.getByRole('heading', { name: 'Help us to improve Shopware' }) });
            const shareStoreDataCheckbox = consentModal.getByRole('checkbox', { name: 'Share store data (anonymous)' });

            await expect(consentModal).toBeVisible();
            await expect(shareStoreDataCheckbox).toHaveCount(0);

        });

        await test.step('Cleanup.', async () => {

            TestDataService.addCreatedRecord('user', user.id);
            await page.close();
        });
    });


