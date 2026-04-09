import { test, expect, Page } from '@fixtures/AcceptanceTest';
import { parseCapturedRequests, setupProductAnalyticsInterceptor, waitForCapturedRequests } from '@helpers/productanalytics-helpers';
import { createNewAdminPageContext, loginToAdministration, User } from '@shopware-ag/acceptance-test-suite';

const PRODUCT_ANALYTICS_ENDPOINT = 'event';

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

        let page: Page;
        let adminUser: User;

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

        await test.step('Intercept all the API calls to product analytics', async () => {

            await page.route(`**/${PRODUCT_ANALYTICS_ENDPOINT}**`, handler);
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
            await expect(page.getByRole('dialog').filter({ has: page.getByRole('heading', { name: 'Help us to improve Shopware' }) }) ).toBeVisible();
        });

        await test.step('Reject all consents.', async () => {

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

            await page.getByRole('button', { name: 'Reject all' }).click();
            await waitForCapturedRequests(capturedRequests, 4);
        });

        await test.step('Validate modal disappeared.', async () => {
            await expect(page.getByRole('dialog').filter({ has: page.getByRole('heading', { name: 'Help us to improve Shopware' }) }) ).toBeHidden();
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
            // no new events should be fired on page navigation after consents are revoked
            await waitForCapturedRequests(capturedRequests, 4);
        });

        await test.step('Cleanup created user.', async () => {

            TestDataService.addCreatedRecord('user', adminUser.id);
        });
});
