import { isSaaSInstance, test } from '@fixtures/AcceptanceTest';
import { expect } from '@playwright/test';
import type { Route, Request } from '@playwright/test';

interface CapturedRequest {
    postData: string;
}

interface ProductAnalyticsContext {
    sw_version: string;
    sw_app_url: string;
    sw_browser_url: string;
    sw_user_agent: string;
    sw_default_language: string;
    sw_default_currency: string;
    sw_screen_width: number;
    sw_screen_height: number;
    sw_screen_orientation: string;
}

interface ProductAnalyticsUser {
    shop_id: string;
    id: string;
}

export interface ProductAnalyticsEvent {
    name: string;
    properties: Record<string, string | number | null>;
    timestamp: number;
    insert_id: string;
    device_id: string;
    session_id: number;
}

export interface ProductAnalyticsRequestPayload {
    context: ProductAnalyticsContext;
    events: ProductAnalyticsEvent[];
    user: ProductAnalyticsUser;
}

const PRODUCT_ANALYTICS_ENDPOINT = 'httpapi';

// Annotate entire file as serial.
test.describe.configure({ mode: 'serial' });

test('As a merchant, I want to make sure admin events are sent correctly.', { tag: '@ProductAnalytics' }, async ({
    ShopAdmin,
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

    test.skip(true, 'Temporarily skipped after removing the PRODUCT_ANALYTICS feature flag.');

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
        const requests = parseCapturedRequests(captured);
        expect(requests).toHaveLength(6);

        const events = requests.flatMap((request) => request.events);
        expect(events).toHaveLength(6);

        const eventNames = events.map(e => e.name);
        expect(eventNames).toEqual([
            'link_visited',
            'page_viewed',
            'link_visited',
            'page_viewed',
            'button_click',
            'page_viewed',
        ]);

        requests.forEach((request) => {
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

            request.events.forEach((event) => {
                expect(event.timestamp).toBeGreaterThan(0);
                expect(event.insert_id).toBeTruthy();
                expect(event.device_id).toBeTruthy();
                expect(event.session_id).toBeGreaterThan(0);
            });
        });

        const [
            firstLinkVisited,
            pageViewed,
            linkVisited,
            pageViewedDetail,
            buttonClicked,
            pageViewedBackToDash,
        ] = events;

        const firstLinkVisitedProps = firstLinkVisited.properties;

        expect(firstLinkVisitedProps.sw_link_href).toBe('#/sw/order/index');
        expect(firstLinkVisitedProps.sw_link_type).toBe('internal');
        expect(firstLinkVisitedProps.sw_page_path).toBe('/sw/dashboard/index');
        expect(firstLinkVisitedProps.sw_page_name).toBe('sw.dashboard.index');

        const pageViewEventProps = pageViewed.properties;

        expect(pageViewEventProps.sw_route_from_name).toBe('sw.dashboard.index');
        expect(pageViewEventProps.sw_route_from_href).toBe('/sw/dashboard/index');
        expect(pageViewEventProps.sw_route_to_name).toBe('sw.order.index');
        expect(pageViewEventProps.sw_route_to_href).toBe('/sw/order/index');
        expect(pageViewEventProps.sw_page_name).toBe('sw.order.index');
        expect(pageViewEventProps.sw_page_path).toBe('/sw/order/index');
        expect(pageViewEventProps.sw_page_full_path).toContain('/sw/order/index?limit=25&page=1&sortBy=orderDateTime&sortDirection=DESC&naturalSorting=false');

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

test('As a merchant, I want to make sure no admin events are sent when I do not consent.', { tag: '@ProductAnalytics' }, async ({
    ShopAdmin,
    AdminDashboard,
    AdminOrderListing,
}) => {
    test.skip(true, 'Temporarily skipped after removing the PRODUCT_ANALYTICS feature flag.');

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

        await AdminDashboard.adminMenuOrder.click();
        await AdminDashboard.adminMenuOrderOverview.click();
        await ShopAdmin.expects(AdminOrderListing.addOrderButton).toBeVisible();
    });

    await test.step('Validate no captured requests for product analytics', async () => {

        // we want to check that something does NOT happen, so we need a hard waitForTimeout, as there is nothing we can actually wait for.
        // so we wait for 3s to ensure that product analytics events would have been captured
        // eslint-disable-next-line playwright/no-wait-for-timeout
        await AdminDashboard.page.waitForTimeout(3000);
        expect(captured.length).toBe(0);
    });
});

function parseCapturedRequests(captured: CapturedRequest[]): ProductAnalyticsRequestPayload[] {
    const requests: ProductAnalyticsRequestPayload[] = [];

    for (const c of captured) {
        if (!c.postData) continue;
        try {
            const parsed: ProductAnalyticsRequestPayload = JSON.parse(c.postData);
            if (parsed && typeof parsed.context === 'object' && Array.isArray(parsed.events)) {
                requests.push(parsed);
            }
        } catch {
            // If not JSON, ignore for now
        }
    }

    return requests;
}
