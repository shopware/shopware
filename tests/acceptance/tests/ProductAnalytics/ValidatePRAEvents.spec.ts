import { isSaaSInstance, test, expect } from '@fixtures/AcceptanceTest';
import { parseCapturedEvents,
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

        // We expect 7 events in total, but they can be in multiple requests
        // 1 anonymous event for consent status change, which is fired when merchant gives consent for product analytics
        // 6 events for user interactions
        const events = parseCapturedEvents(capturedRequests);
        expect(events).toHaveLength(7);

        const eventIds = events.map(e => e.event_id);
        expect(eventIds).toEqual([undefined, 0, 1, 2, 3, 4, 5]);

        const eventTypes = events.map(e => e.event_type);
        expect(eventTypes).toEqual([
            'consent_status_change', // anonymous event undefined event_id
            'link_visited',   // event_id 0
            'page_viewed',    // event_id 1
            'link_visited',   // event_id 2
            'page_viewed',    // event_id 3
            'button_click',   // event_id 4
            'page_viewed',    // event_id 5
        ]);

        const [
            consentStatusChange, // anonymous event with undefined event_id
            firstLinkVisited,    // event_id 0
            pageViewed,          // event_id 1
            linkVisited,         // event_id 2
            pageViewedDetail,    // event_id 3
            buttonClicked,       // event_id 4
            pageViewedBackToDash,// event_id 5
        ] = events;

        // ----------------------
        // event_id = undefined: anonymous event
        // ----------------------
        const consentStatusChangeProps = consentStatusChange.event_properties;
        expect(consentStatusChangeProps.consent).toBe('product_analytics');
        expect(consentStatusChangeProps.status).toBe('accepted');

        // ----------------------
        // event_id = 0: first Link Visited (dashboard -> order listing)
        // ----------------------
        const firstLinkVisitedProps = firstLinkVisited.event_properties;

        expect(firstLinkVisitedProps.sw_link_href).toBe('#/sw/order/index');
        expect(firstLinkVisitedProps.sw_link_type).toBe('internal');
        expect(firstLinkVisitedProps.sw_page_path).toBe('/sw/profile/index/privacy-preferences');
        expect(firstLinkVisitedProps.sw_page_name).toBe('sw.profile.index.privacyPreferences');

        // ----------------------
        // event_id = 1: first Page Viewed (dashboard -> order listing)
        // ----------------------
        const pageViewEventProps = pageViewed.event_properties;

        expect(pageViewEventProps.sw_route_from_name).toBe('sw.profile.index.privacyPreferences');
        expect(pageViewEventProps.sw_route_from_href).toBe('/sw/profile/index/privacy-preferences');
        expect(pageViewEventProps.sw_route_to_name).toBe('sw.order.index');
        expect(pageViewEventProps.sw_route_to_href).toBe('/sw/order/index');
        expect(pageViewEventProps.sw_page_name).toBe('sw.order.index');
        expect(pageViewEventProps.sw_page_path).toBe('/sw/order/index');
        expect(pageViewEventProps.sw_page_full_path).toContain('/sw/order/index?limit=25&page=1&sortBy=orderDateTime&sortDirection=DESC&naturalSorting=false');

        // ----------------------
        // event_id = 2: Link Visited (clicking into order detail from listing)
        // ----------------------
        const linkVisitedProps = linkVisited.event_properties;

        expect(linkVisitedProps.sw_link_href).toContain(`#/sw/order/detail/${order.id}`);
        expect(linkVisitedProps.sw_page_full_path).toContain('/sw/order/index?limit=25&page=1&sortBy=orderDateTime&sortDirection=DESC&naturalSorting=false&grid.filter.order=null')
        expect(linkVisitedProps.sw_link_type).toBe('internal');
        expect(linkVisitedProps.sw_page_path).toBe('/sw/order/index');
        expect(linkVisitedProps.sw_page_name).toBe('sw.order.index');

        // ----------------------
        // event_id = 3: Page Viewed (order detail.general)
        // ----------------------
        const pageViewedDetailProps = pageViewedDetail.event_properties;

        expect(pageViewedDetailProps.sw_route_from_name).toBe('sw.order.index');
        expect(pageViewedDetailProps.sw_route_from_href).toBe('/sw/order/index');
        expect(pageViewedDetailProps.sw_route_to_name).toBe('sw.order.detail.general');
        expect(pageViewedDetailProps.sw_route_to_href).toContain('/sw/order/detail/');
        expect(pageViewedDetailProps.sw_page_name).toBe('sw.order.detail.general');
        expect(pageViewedDetailProps.sw_page_path).toContain('/sw/order/detail/');
        expect(pageViewedDetailProps.sw_page_full_path).toBe(`/sw/order/detail/${order.id}/general`);

        // ----------------------
        // event_id = 4: Button Click
        // ----------------------
        const buttonEventProps = buttonClicked.event_properties;

        expect(buttonEventProps.sw_element_id).toBe('sw-order-detail.save-edits');
        expect(buttonEventProps.sw_page_full_path).toBe(`/sw/order/detail/${order.id}/general`);
        expect(buttonEventProps.sw_page_path).toBe(`/sw/order/detail/${order.id}/general`);
        expect(buttonEventProps.sw_page_name).toBe('sw.order.detail.general');

        // ----------------------
        // event_id = 5: final Page Viewed (back to dashboard)
        // ----------------------
        const pageViewedBackToDashProps = pageViewedBackToDash.event_properties;

        expect(pageViewedBackToDashProps.sw_route_from_name).toBe('sw.order.detail.general');
        expect(pageViewedBackToDashProps.sw_route_from_href).toBe(`/sw/order/detail/${order.id}/general`);
        expect(pageViewedBackToDashProps.sw_route_to_name).toBe('sw.dashboard.index');
        expect(pageViewedBackToDashProps.sw_route_to_href).toBe('/sw/dashboard/index');
        expect(pageViewedBackToDashProps.sw_page_name).toBe('sw.dashboard.index');
        expect(pageViewedBackToDashProps.sw_page_path).toBe('/sw/dashboard/index');
    });
});
