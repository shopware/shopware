import { isSaaSInstance, test } from '@fixtures/AcceptanceTest';
import { expect } from '@playwright/test';
import type { Route, Request } from '@playwright/test';

interface CapturedRequest {
    postData: string;
}

export interface AmplitudeEvent {
    event_type: string;
    event_properties: Record<string, string | number>;
    event_id: number;
}

export interface AmplitudeRequestPayload {
    events: AmplitudeEvent[];
}

const PRODUCT_ANALYTICS_ENDPOINT = 'httpapi';

// Annotate entire file as serial.
test.describe.configure({ mode: 'serial' });

test('As a merchant, I want to make sure admin events are sent correctly.', { tag: '@ProductAnalytics' }, async ({
    ShopAdmin,
    FeatureService,
    AdminDashboard,
    AdminOrderListing,
    AdminOrderDetail,
    TestDataService,
}) => {

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

    test.skip(!(await FeatureService.isEnabled('PRODUCT_ANALYTICS')), 'Product Analytics feature flag is not enabled.');

    const product = await TestDataService.createBasicProduct();
    const customer = await TestDataService.createCustomer();
    const order = await TestDataService.createOrder([{ product: product, quantity: 1 }], customer);

    await test.step('Intercept all the API calls to product analytics', async () => {

        await AdminDashboard.page.route(`**/${PRODUCT_ANALYTICS_ENDPOINT}`, requestHandler);
    });

    await test.step('Set consent for product analytics', async () => {
        // TO-DO: implement via UI once available and Feature flag is disabled by default
    });

    await test.step('Navigate via link to order page from dashboard', async () => {

        const requestPromise = AdminDashboard.page.waitForRequest(`**/${PRODUCT_ANALYTICS_ENDPOINT}`);
        await AdminDashboard.adminMenuOrder.click();
        await AdminDashboard.adminMenuOrderOverview.click();
        const request = await requestPromise;
        expect(request.url()).toContain(PRODUCT_ANALYTICS_ENDPOINT);

        await ShopAdmin.expects(AdminOrderListing.addOrderButton).toBeVisible();
    });

    await test.step('Navigate via link to detail order page', async () => {

        const requestPromise = AdminDashboard.page.waitForRequest(`**/${PRODUCT_ANALYTICS_ENDPOINT}`);
        const orderRow = await AdminOrderListing.getLineItemByOrderNumber(order.orderNumber);
        await ShopAdmin.expects(orderRow.orderNumberText).toBeVisible()
        await orderRow.orderNumberText.click();
        const request = await requestPromise;
        expect(request.url()).toContain(PRODUCT_ANALYTICS_ENDPOINT);
    });

    await test.step('Navigate via button to save order', async () => {

        const requestPromise = AdminDashboard.page.waitForRequest(`**/${PRODUCT_ANALYTICS_ENDPOINT}`);
        await ShopAdmin.expects(AdminOrderDetail.saveButton).toBeVisible();
        await ShopAdmin.expects(AdminOrderDetail.contextMenuButton).toBeVisible()
        await AdminOrderDetail.saveButton.click();
        const request = await requestPromise;
        expect(request.url()).toContain(PRODUCT_ANALYTICS_ENDPOINT);

        await ShopAdmin.expects(AdminOrderDetail.contextMenuButton).toBeVisible()
    });

    await test.step('Navigate via page view to dashboard page', async () => {

        const requestPromise = AdminDashboard.page.waitForRequest(`**/${PRODUCT_ANALYTICS_ENDPOINT}`);
        await ShopAdmin.goesTo(AdminDashboard.url());
        const request = await requestPromise;
        expect(request.url()).toContain(PRODUCT_ANALYTICS_ENDPOINT);

        await ShopAdmin.expects(AdminDashboard.adminMenuOrder).toBeVisible();
        // eslint-disable-next-line playwright/no-conditional-in-test
        if (!await isSaaSInstance(TestDataService.AdminApiClient)) {
            await ShopAdmin.expects(AdminDashboard.welcomeHeadline).toBeVisible();
        }
    });

    await test.step('Validate captured requests for product analytics', async () => {

        const events = parseCapturedEvents(captured);
        expect(events).toHaveLength(6);

        const eventIds = events.map(e => e.event_id);
        expect(eventIds).toEqual([1, 2, 3, 4, 5, 6]);

        const eventTypes = events.map(e => e.event_type);
        expect(eventTypes).toEqual([
            'Link Visited',   // event_id 1
            'Page Viewed',    // event_id 2
            'Link Visited',   // event_id 3
            'Page Viewed',    // event_id 4
            'Button Click',   // event_id 5
            'Page Viewed',    // event_id 6
        ]);

        const [
            firstLinkVisited,    // event_id 1
            pageViewed,          // event_id 2
            linkVisited,         // event_id 3
            pageViewedDetail,    // event_id 4
            buttonClicked,       // event_id 5
            pageViewedBackToDash,// event_id 6
        ] = events;

        // ----------------------
        // event_id = 1: first Link Visited (dashboard -> order listing)
        // ----------------------
        const firstLinkVisitedProps = firstLinkVisited.event_properties;

        expect(firstLinkVisitedProps.sw_link_href).toBe('#/sw/order/index');
        expect(firstLinkVisitedProps.sw_link_type).toBe('internal');
        expect(firstLinkVisitedProps.sw_page_path).toBe('/sw/dashboard/index');
        expect(firstLinkVisitedProps.sw_page_name).toBe('sw.dashboard.index');

        // ----------------------
        // event_id = 2: first Page Viewed (dashboard -> order listing)
        // ----------------------
        const pageViewEventProps = pageViewed.event_properties;

        expect(pageViewEventProps.sw_route_from_name).toBe('sw.dashboard.index');
        expect(pageViewEventProps.sw_route_from_href).toBe('/sw/dashboard/index');
        expect(pageViewEventProps.sw_route_to_name).toBe('sw.order.index');
        expect(pageViewEventProps.sw_route_to_href).toBe('/sw/order/index');
        expect(pageViewEventProps.sw_page_name).toBe('sw.order.index');
        expect(pageViewEventProps.sw_page_path).toBe('/sw/order/index');
        expect(pageViewEventProps.sw_page_full_path).toContain('/sw/order/index?limit=25&page=1&sortBy=orderDateTime&sortDirection=DESC&naturalSorting=false');

        // ----------------------
        // event_id = 3: Link Visited (clicking into order detail from listing)
        // ----------------------
        const linkVisitedProps = linkVisited.event_properties;

        expect(linkVisitedProps.sw_link_href).toContain(`#/sw/order/detail/${order.id}`);
        expect(linkVisitedProps.sw_page_full_path).toContain('/sw/order/index?limit=25&page=1&sortBy=orderDateTime&sortDirection=DESC&naturalSorting=false&grid.filter.order=null')
        expect(linkVisitedProps.sw_link_type).toBe('internal');
        expect(linkVisitedProps.sw_page_path).toBe('/sw/order/index');
        expect(linkVisitedProps.sw_page_name).toBe('sw.order.index');

        // ----------------------
        // event_id = 4: Page Viewed (order detail.general)
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
        // event_id = 5: Button Click
        // ----------------------
        const buttonEventProps = buttonClicked.event_properties;

        expect(buttonEventProps.sw_element_id).toBe('sw-order-detail.save-edits');
        expect(buttonEventProps.sw_page_full_path).toBe(`/sw/order/detail/${order.id}/general`);
        expect(buttonEventProps.sw_page_path).toBe(`/sw/order/detail/${order.id}/general`);
        expect(buttonEventProps.sw_page_name).toBe('sw.order.detail.general');

        // ----------------------
        // event_id = 6: final Page Viewed (back to dashboard)
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

test('As a merchant, I want to make sure no admin events are sent when I do not consent.', { tag: '@ProductAnalytics' }, async ({
    ShopAdmin,
    AdminDashboard,
    AdminOrderListing,
}) => {

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

    await test.step('Do not set consent for product analytics', async () => {
        // TO-DO: implement via UI once available and Feature flag is disabled by default
    });

   await test.step('Intercept all the API calls to product analytics', async () => {

        await AdminDashboard.page.route(`**/${PRODUCT_ANALYTICS_ENDPOINT}`, requestHandler);
    });

    await test.step('Navigate via link to order page from dashboard', async () => {

        const requestPromise = AdminDashboard.page.waitForRequest(`**/${PRODUCT_ANALYTICS_ENDPOINT}`, { timeout: 3000 });
        await AdminDashboard.adminMenuOrder.click();
        await AdminDashboard.adminMenuOrderOverview.click();
        await ShopAdmin.expects(requestPromise).rejects.toThrow();
        await ShopAdmin.expects(AdminOrderListing.addOrderButton).toBeVisible();
    });

    await test.step('Validate no captured requests for product analytics', async () => {

        expect(captured.length).toBe(0);
    });
});

function parseCapturedEvents(captured: CapturedRequest[]): AmplitudeEvent[] {
    const events: AmplitudeEvent[] = [];

    for (const c of captured) {
        if (!c.postData) continue;
        try {
            const parsed: AmplitudeRequestPayload = JSON.parse(c.postData);
            if (Array.isArray(parsed.events)) {
                events.push(...parsed.events);
            }
        } catch {
            // If not JSON, ignore for now
        }
    }

    return events;
}
