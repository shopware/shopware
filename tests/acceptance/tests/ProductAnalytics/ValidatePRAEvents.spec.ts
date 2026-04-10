import { isSaaSInstance, test, expect } from '@fixtures/AcceptanceTest';
import { parseCapturedRequests,
    setupProductAnalyticsInterceptor, waitForCapturedRequests,
} from '@helpers/productanalytics-helpers';


const PRODUCT_ANALYTICS_ENDPOINT = 'event';

// Settings for running tests in serial mode to avoid interference
// Test modifies user consent and captures network requests, where other tests if run in parallel
test.describe.configure({ mode: 'serial' });

test('As a merchant, I want to make sure admin events are sent correctly.', { tag: '@ProductAnalytics' }, async ({
    ShopAdmin,
    AdminDashboard,
    AdminOrderListing,
    AdminOrderDetail,
    TestDataService,
    AdminYourProfile,
}) => {

    const { capturedRequests, handler } = setupProductAnalyticsInterceptor();

    const product = await TestDataService.createBasicProduct();
    const customer = await TestDataService.createCustomer();
    const order = await TestDataService.createOrder([{ product: product, quantity: 1 }], customer);

    await test.step('Intercept all the API calls to product analytics', async () => {

        await AdminDashboard.page.route(`**/${PRODUCT_ANALYTICS_ENDPOINT}**`, handler);
    });

    await test.step('Set consent for product analytics to track events', async () => {
        // There are 2 options. Once via UI and once via API.
        /** Option 1: via API, issue is the AdminAPICLient is not USer specific, so that the consent can not set. Refactoring is necessary
         * const request = await TestDataService.AdminApiClient.post('consents/accept?_response=detail', {
         *             data: {
         *                 consent: 'product_analytics',
         *             },
         *         });
         *         const errors = await request.json();
         *         console.log(errors)
         *         await AdminApiContext.create();
         */
        await ShopAdmin.goesTo(AdminYourProfile.url('privacy-preferences'));
        await AdminYourProfile.dataSharingUsageDataCheckbox.click();
        await waitForCapturedRequests(capturedRequests, 1);
    });

    await test.step('Navigate via link to order page from dashboard', async () => {

        await AdminDashboard.adminMenuOrder.click();
        await AdminDashboard.adminMenuOrderOverview.click();
        await waitForCapturedRequests(capturedRequests, 2);
        await ShopAdmin.expects(AdminOrderListing.addOrderButton).toBeVisible();
    });

    await test.step('Navigate via link to detail order page', async () => {

        const orderRow = await AdminOrderListing.getLineItemByOrderNumber(order.orderNumber);
        await ShopAdmin.expects(orderRow.orderNumberText).toBeVisible()
        await orderRow.orderNumberText.click();
        await waitForCapturedRequests(capturedRequests, 3);
    });

    await test.step('Navigate via button to save order', async () => {

        await ShopAdmin.expects(AdminOrderDetail.saveButton).toBeVisible();
        await ShopAdmin.expects(AdminOrderDetail.contextMenuButton).toBeVisible()
        await AdminOrderDetail.saveButton.click();
        await waitForCapturedRequests(capturedRequests, 4);
        await ShopAdmin.expects(AdminOrderDetail.contextMenuButton).toBeVisible()
    });

    await test.step('Navigate via page view to dashboard page', async () => {

        await ShopAdmin.goesTo(AdminDashboard.url());
        await waitForCapturedRequests(capturedRequests, 5);

        await ShopAdmin.expects(AdminDashboard.adminMenuOrder).toBeVisible();
        // eslint-disable-next-line playwright/no-conditional-in-test
        if (!await isSaaSInstance(TestDataService.AdminApiClient)) {
            await ShopAdmin.expects(AdminDashboard.welcomeHeadline).toBeVisible();
        }
    });

    await test.step('Validate captured requests for product analytics', async () => {

        // We expect 9 events in total, but they can be in multiple requests
        // 1 anonymous event for consent status change, which is fired when merchant gives consent for product analytics
        // 8 events for user interactions
        const requests = parseCapturedRequests(capturedRequests);
        expect(requests).toHaveLength(5);

        const events = requests.flatMap((request) => request.events);
        expect(events).toHaveLength(9);

        const eventNames = events.map(e => e.name);
        expect(eventNames).toEqual([
            'consent_status_change',
            'link_visited',
            'page_viewed',
            'page_viewed',
            'page_viewed',
            'link_visited',
            'page_viewed',
            'button_click',
            'page_viewed',
        ]);

        const anonymousRequests = requests.filter((request) => request.user?.id == null);
        const authenticatedRequests = requests.filter((request) => request.user?.id != null);

        expect(anonymousRequests).toHaveLength(1);
        expect(authenticatedRequests).toHaveLength(4);

        for (const request of anonymousRequests) {
            expect(request.context.sw_version).toBeTruthy();
            expect(request.context.sw_app_url).toBeUndefined();

            for (const event of request.events) {
                expect(event.timestamp).toBeGreaterThan(0);
                expect(event.name).toBeTruthy();
                expect(event.device_id).toBeUndefined();
                expect(event.properties).toBeTruthy();
            }
        }

        for (const request of authenticatedRequests) {
            expect(request.user.shop_id).toBeTruthy();
            expect(request.user.id).toBeTruthy();
            expect(request.context.sw_version).toBeTruthy();
            expect(request.context.sw_app_url).toBeTruthy();
            expect(request.context.sw_browser_url).toBeTruthy();
            expect(request.context.sw_user_agent).toBeTruthy();
            expect(request.context.sw_default_language).toBeTruthy();
            expect(request.context.sw_default_currency).toBeTruthy();
            expect(request.context.sw_screen_width).toBeGreaterThan(0);
            expect(request.context.sw_screen_height).toBeGreaterThan(0);
            expect(request.context.sw_screen_orientation).toBeTruthy();

            for (const event of request.events) {
                expect(event.timestamp).toBeGreaterThan(0);
                expect(event.insert_id).toBeTruthy();
                expect(event.device_id).toBeTruthy();
                expect(event.session_id).toBeGreaterThan(0);
            }
        }

        const [
            consentStatusChange,
            firstLinkVisited,
            pageViewed,
            pageViewed1,
            pageViewed2,
            linkVisited,
            pageViewedDetail,
            buttonClicked,
            pageViewedBackToDash,
        ] = events;

        const consentStatusChangeProps = consentStatusChange.properties;

        expect(consentStatusChangeProps.consent).toBe('product_analytics');
        expect(consentStatusChangeProps.status).toBe('accepted');

        const firstLinkVisitedProps = firstLinkVisited.properties;

        expect(firstLinkVisitedProps.sw_link_href).toBe('#/sw/order/index');
        expect(firstLinkVisitedProps.sw_link_type).toBe('internal');
        expect(firstLinkVisitedProps.sw_page_path).toBe('/sw/profile/index/privacy-preferences');
        expect(firstLinkVisitedProps.sw_page_name).toBe('sw.profile.index.privacyPreferences');

        const pageViewEventProps = pageViewed.properties;

        expect(pageViewEventProps.sw_route_from_name).toBe('sw.profile.index.privacyPreferences');
        expect(pageViewEventProps.sw_route_from_href).toBe('/sw/profile/index/privacy-preferences');
        expect(pageViewEventProps.sw_route_to_name).toBe('sw.order.index');
        expect(pageViewEventProps.sw_route_to_href).toBe('/sw/order/index');
        expect(pageViewEventProps.sw_page_name).toBe('sw.order.index');
        expect(pageViewEventProps.sw_page_path).toBe('/sw/order/index');
        expect(pageViewEventProps.sw_page_full_path).toContain('/sw/order/index');

        const pageView1EventProps = pageViewed1.properties;

        expect(pageView1EventProps.sw_route_from_name).toBe('sw.order.index');
        expect(pageView1EventProps.sw_route_from_href).toBe('/sw/order/index');
        expect(pageView1EventProps.sw_route_to_name).toBe('sw.order.index');
        expect(pageView1EventProps.sw_route_to_href).toBe('/sw/order/index');
        expect(pageView1EventProps.sw_page_name).toBe('sw.order.index');
        expect(pageView1EventProps.sw_page_path).toBe('/sw/order/index');
        expect(pageView1EventProps.sw_page_full_path).toContain('/sw/order/index?limit=25&page=1&sortBy=orderDateTime&sortDirection=DESC&naturalSorting=false');

        const pageView2EventProps = pageViewed2.properties;

        expect(pageView2EventProps.sw_route_from_name).toBe('sw.order.index');
        expect(pageView2EventProps.sw_route_from_href).toBe('/sw/order/index');
        expect(pageView2EventProps.sw_route_to_name).toBe('sw.order.index');
        expect(pageView2EventProps.sw_route_to_href).toBe('/sw/order/index');
        expect(pageView2EventProps.sw_page_name).toBe('sw.order.index');
        expect(pageView2EventProps.sw_page_path).toBe('/sw/order/index');
        expect(pageView2EventProps.sw_page_full_path).toContain('/sw/order/index?limit=25&page=1&sortBy=orderDateTime&sortDirection=DESC&naturalSorting=false&grid.filter.order=null');

        const linkVisitedProps = linkVisited.properties;

        expect(linkVisitedProps.sw_link_href).toContain(`#/sw/order/detail/${order.id}`);
        expect(linkVisitedProps.sw_page_full_path).toContain('/sw/order/index?limit=25&page=1&sortBy=orderDateTime&sortDirection=DESC&naturalSorting=false&grid.filter.order=null')
        expect(linkVisitedProps.sw_link_type).toBe('internal');
        expect(linkVisitedProps.sw_page_path).toBe('/sw/order/index');
        expect(linkVisitedProps.sw_page_name).toBe('sw.order.index');

        const pageViewedDetailProps = pageViewedDetail.properties;

        expect(pageViewedDetailProps.sw_route_from_name).toBe('sw.order.index');
        expect(pageViewedDetailProps.sw_route_from_href).toBe('/sw/order/index');
        expect(pageViewedDetailProps.sw_route_to_name).toBe('sw.order.detail.general');
        expect(pageViewedDetailProps.sw_route_to_href).toContain('/sw/order/detail/');
        expect(pageViewedDetailProps.sw_page_name).toBe('sw.order.detail.general');
        expect(pageViewedDetailProps.sw_page_path).toContain('/sw/order/detail/');
        expect(pageViewedDetailProps.sw_page_full_path).toBe(`/sw/order/detail/${order.id}/general`);

        const buttonEventProps = buttonClicked.properties;

        expect(buttonEventProps.sw_element_id).toBe('sw-order-detail.save-edits');
        expect(buttonEventProps.sw_page_full_path).toBe(`/sw/order/detail/${order.id}/general`);
        expect(buttonEventProps.sw_page_path).toBe(`/sw/order/detail/${order.id}/general`);
        expect(buttonEventProps.sw_page_name).toBe('sw.order.detail.general');

        const pageViewedBackToDashProps = pageViewedBackToDash.properties;

        expect(pageViewedBackToDashProps.sw_route_from_name).toBe('sw.order.detail.general');
        expect(pageViewedBackToDashProps.sw_route_from_href).toBe(`/sw/order/detail/${order.id}/general`);
        expect(pageViewedBackToDashProps.sw_route_to_name).toBe('sw.dashboard.index');
        expect(pageViewedBackToDashProps.sw_route_to_href).toBe('/sw/dashboard/index');
        expect(pageViewedBackToDashProps.sw_page_name).toBe('sw.dashboard.index');
        expect(pageViewedBackToDashProps.sw_page_path).toBe('/sw/dashboard/index');
    });
});
