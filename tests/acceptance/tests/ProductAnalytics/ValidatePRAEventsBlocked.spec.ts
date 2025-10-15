import { isSaaSInstance, test } from '@fixtures/AcceptanceTest';
import { expect } from '@playwright/test';
import type { Route, Request } from '@playwright/test';

interface CapturedRequest {
    url: string,
    postData?: string | null;
    headers: Record<string, string>;
}

export interface AmplitudeEvent {
    device_id: string;
    session_id: number;
    time: number;
    app_version: string;
    insert_id: string;
    event_type: string;
    event_properties: Record<string, string | number>;
    event_id: number;
    library: string;
    user_agent: string;
}

export interface AmplitudeRequestPayload {
    api_key: string;
    events: AmplitudeEvent[];
    options?: Record<string, unknown>;
    client_upload_time?: string;
    request_metadata?: Record<string, unknown>;
}

const PRODUCT_ANALYTICS_HOST = 'https://api.eu.amplitude.com/2/httpapi';
const DEFAULT_TIMEOUT = 2000;


test.skip('As a merchant, I want to make sure admin events are sent correctly.', { tag: '@ProductAnalytics' }, async ({
    ShopAdmin,
    FeatureService,
    AdminDashboard,
    AdminOrderListing,
    AdminOrderDetail,
    TestDataService,
    }) => {

    const captured: CapturedRequest[] = [];
    const handler = async (route: Route) => {
        const req: Request = route.request();
        captured.push({
            url: req.url(),
            postData: req.postData(),
            headers: req.headers(),
        });
        await route.abort();
    };

    test.skip(!(await FeatureService.isEnabled('PRODUCT_ANALYTICS')), 'Product Analytics feature flag is not enabled.');

    const product = await TestDataService.createBasicProduct();
    const customer = await TestDataService.createCustomer();
    const order = await TestDataService.createOrder([{ product: product, quantity: 1 }], customer);

    await test.step('Intercept and assert the api call to product analytics', async () => {

        // Intercept the exact Amplitude ingestion endpoint.
        await AdminDashboard.page.route(PRODUCT_ANALYTICS_HOST, handler);

        // small pause for app init
        await AdminDashboard.page.waitForTimeout(DEFAULT_TIMEOUT);
    });

    await test.step('Set consent for product analytics', async () => {
        // TO-DO: implement via UI once available and Feature flag is disabled by default
    });

    await test.step('Navigate via Link to order page from Dashboard', async () => {
        await AdminDashboard.adminMenuOrder.click();
        await AdminDashboard.adminMenuOrderOverview.click();
        await ShopAdmin.expects(AdminOrderListing.addOrderButton).toBeVisible();
        // small pause for request to be sent
        await AdminDashboard.page.waitForTimeout(DEFAULT_TIMEOUT);
    });

    await test.step('Navigate via Link to detail order page', async () => {
       const orderRow = await AdminOrderListing.getLineItemByOrderNumber(order.orderNumber);
       await ShopAdmin.expects(orderRow.orderNumberText).toBeVisible()
       await orderRow.orderNumberText.click();
        // small pause for request to be sent
       await AdminDashboard.page.waitForTimeout(DEFAULT_TIMEOUT);
    });

    await test.step('Navigate via Button to save order', async () => {
        await ShopAdmin.expects(AdminOrderDetail.saveButton).toBeVisible();
        await ShopAdmin.expects(AdminOrderDetail.contextMenuButton).toBeVisible()
        await AdminOrderDetail.saveButton.click();
        await ShopAdmin.expects(AdminOrderDetail.contextMenuButton).toBeVisible()
        // small pause for request to be sent
        await AdminDashboard.page.waitForTimeout(DEFAULT_TIMEOUT);
    });

    await test.step('Navigate via page view to dashboard page', async () => {
        await ShopAdmin.goesTo(AdminDashboard.url());
        await ShopAdmin.expects(AdminDashboard.adminMenuOrder).toBeVisible();
        // eslint-disable-next-line playwright/no-conditional-in-test
        if (!await isSaaSInstance(TestDataService.AdminApiClient)) {
            await ShopAdmin.expects(AdminDashboard.welcomeHeadline).toBeVisible();
        }
        // small pause for request to be sent
        await AdminDashboard.page.waitForTimeout(DEFAULT_TIMEOUT);
    });

    await test.step('Validate captured requests for product analytics', async () => {
        // 5 requests should be captured (2x page view, 2x link visit, 1x button click)
        // Potentially flaky if requests are delayed
        expect(captured.length).toBe(5);

        const allEvents = parseCapturedEvents(captured);

        const findByName = (name: string) =>
            allEvents.find(
                (e) =>
                    (typeof e.event_type === 'string' && e.event_type === name)
            );

        const pageViewed = findByName('Page Viewed');
        const buttonClicked = findByName('Button Click');
        const linkVisited = findByName('Link Visited');

        expect(pageViewed, 'expected a Page Viewed event attempt').toBeTruthy();
        expect(buttonClicked, 'expected a Button Click event attempt').toBeTruthy();
        expect(linkVisited, 'expected a Link Visited event attempt').toBeTruthy();

        // Validate first page view event dashboard -> order listing
        const pageViewEventProperties = pageViewed.event_properties;

        expect(pageViewEventProperties.sw_route_from_name).toBeDefined();
        expect(pageViewEventProperties.sw_route_from_href).toBeDefined();
        expect(pageViewEventProperties.sw_route_to_name).toBeDefined();
        expect(pageViewEventProperties.sw_route_to_href).toBeDefined();
        expect(pageViewEventProperties.sw_page_name).toBeDefined();
        expect(pageViewEventProperties.sw_page_path).toBeDefined();

        // Value assertions (ensures correct navigation transition)
        expect(pageViewEventProperties.sw_route_from_name).toBe('sw.dashboard.index');
        expect(pageViewEventProperties.sw_route_from_href).toBe('/sw/dashboard/index');
        expect(pageViewEventProperties.sw_route_to_name).toBe('sw.order.index');
        expect(pageViewEventProperties.sw_route_to_href).toBe('/sw/order/index');
        expect(pageViewEventProperties.sw_page_name).toBe('sw.order.index');
        expect(pageViewEventProperties.sw_page_path).toBe('/sw/order/index');

        // Validate button click event
        const buttonEventProperties = buttonClicked.event_properties;

        expect(buttonEventProperties.sw_element_id).toBeDefined();
        expect(buttonEventProperties.sw_page_full_path).toBeDefined();
        expect(buttonEventProperties.sw_page_path).toBeDefined();
        expect(buttonEventProperties.sw_page_name).toBeDefined();

        expect(buttonEventProperties.sw_element_id).toBe('sw-order-detail.save-edits');
        expect(buttonEventProperties.sw_page_full_path).toBe(`/sw/order/detail/${order.id}/general`);
        expect(buttonEventProperties.sw_page_path).toBe(`/sw/order/detail/${order.id}/general`);
        expect(buttonEventProperties.sw_page_name).toBe(`sw.order.detail.general`);

        // Validate link visit event (to dashboard)
        const linkVisitedEventProperties = linkVisited.event_properties;

        expect(linkVisitedEventProperties.sw_link_href).toBeDefined();
        expect(linkVisitedEventProperties.sw_link_type).toBeDefined();
        expect(linkVisitedEventProperties.sw_page_path).toBeDefined();
        expect(linkVisitedEventProperties.sw_page_name).toBeDefined();

        expect(linkVisitedEventProperties.sw_link_href).toBe('#/sw/order/index');
        expect(linkVisitedEventProperties.sw_link_type).toBe('internal');
        expect(linkVisitedEventProperties.sw_page_path).toBe('/sw/dashboard/index');
        expect(linkVisitedEventProperties.sw_page_name).toBe('sw.dashboard.index');

        // Cleanup route so other tests are not affected
        await AdminDashboard.page.unroute(PRODUCT_ANALYTICS_HOST, handler);
    });
});

test.beforeEach(
    async ({FeatureService}) => {
        // Ensure the feature is disabled
        if (await FeatureService.isEnabled('PRODUCT_ANALYTICS')) {
            console.log('Disabling PRODUCT_ANALYTICS feature flag for test');
            await FeatureService.disable('PRODUCT_ANALYTICS');
        }
    }
);

test('As a merchant, I want to make sure no admin events are sent when I do not consent.', { tag: '@ProductAnalytics' }, async ({
    ShopAdmin,
    FeatureService,
    AdminDashboard,
    AdminOrderListing,
    }) => {

   test.skip(!(await FeatureService.isEnabled('PRODUCT_ANALYTICS')), 'Product Analytics feature flag is not enabled.');

    const captured: CapturedRequest[] = [];
    const handler = async (route: Route) => {
        const req: Request = route.request();
        captured.push({
            url: req.url(),
            postData: req.postData(),
            headers: req.headers(),
        });
        await route.abort();
    };

    await test.step('Do not set consent for product analytics', async () => {
        // TO-DO: implement via UI once available and Feature flag is disabled by default

    });

   await test.step('Intercept and assert the api call to product analytics', async () => {

        // Intercept the exact Amplitude ingestion endpoint.
        await AdminDashboard.page.route(PRODUCT_ANALYTICS_HOST, handler);

        // small pause for app init
        await AdminDashboard.page.waitForTimeout(DEFAULT_TIMEOUT);
    });



    await test.step('Navigate via Link to order page from Dashboard', async () => {
        await AdminDashboard.adminMenuOrder.click();
        await AdminDashboard.adminMenuOrderOverview.click();
        await ShopAdmin.expects(AdminOrderListing.addOrderButton).toBeVisible();
        // small pause for request to be sent
        await AdminDashboard.page.waitForTimeout(DEFAULT_TIMEOUT);
    });

    await test.step('Validate no captured requests for product analytics', async () => {

        console.log(captured)

        // No requests should be captured
        expect(captured.length).toBe(0);

        const allEvents = parseCapturedEvents(captured);
        expect(allEvents.length).toBe(0);

        // Cleanup route so other tests are not affected
        await AdminDashboard.page.unroute(PRODUCT_ANALYTICS_HOST, handler);
    });
});

function parseCapturedEvents(captured: CapturedRequest[]): AmplitudeEvent[] {
    const events: AmplitudeEvent[] = [];

    for (const c of captured) {
        if (!c.postData) continue;
        try {
            const parsed: AmplitudeRequestPayload | AmplitudeEvent[] = JSON.parse(c.postData);
            if (Array.isArray(parsed)) {
                events.push(...parsed);
            } else if (Array.isArray(parsed.events)) {
                events.push(...parsed.events);
            }
        } catch {
            // If not JSON, ignore for now
        }
    }

    return events;
}
