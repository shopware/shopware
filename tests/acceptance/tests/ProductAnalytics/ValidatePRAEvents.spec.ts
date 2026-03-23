import { isSaaSInstance, test } from '@fixtures/AcceptanceTest';
import { expect } from '@playwright/test';
import type { Page, Response, Route, Request } from '@playwright/test';
import { AdminPageObjects } from '@shopware-ag/acceptance-test-suite';
import { prepareProductAnalyticsConsentModal } from '../../helpers/product-analytics-consent';
import { createStableAdminPage } from '../../helpers/stable-admin-page';

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

const PRODUCT_ANALYTICS_ENDPOINT = /\/event(?:$|\?)/;
const TRACKED_ADMIN_EVENT_TYPES = new Set(['link_visited', 'page_viewed', 'button_click']);
const ALLOW_ALL_BUTTON = /Allow All|Share all data|Alle akzeptieren|Alle Daten teilen/;
const REJECT_ALL_BUTTON = /Reject All|Share nothing|Alle ablehnen|Ablehnen/;

function waitForConsentResponse(
    page: Page,
    action: 'accept' | 'revoke',
    consent: 'backend_data' | 'product_analytics',
): Promise<Response> {
    return page.waitForResponse((response: Response) => {
        if (!response.url().includes(`/api/consents/${action}`)) {
            return false;
        }

        if (response.request().method() !== 'POST') {
            return false;
        }

        try {
            return response.request().postDataJSON()?.consent === consent;
        } catch {
            return false;
        }
    });
}

async function removeSymfonyDebugToolbar(page: Page): Promise<void> {
    await page.evaluate(() => {
        document.querySelectorAll('.sf-toolbar').forEach((element) => {
            element.remove();
        });
    });
}

async function expectCapturedEventCount(captured: CapturedRequest[], expectedCount: number): Promise<void> {
    await expect.poll(() => parseCapturedEvents(captured).length).toBe(expectedCount);
}

// Annotate entire file as serial.
test.describe.configure({ mode: 'serial' });

test('As a merchant, I want to make sure admin events are sent correctly.', {
    tag: ['@ProductAnalytics', '@ProductAnalyticsConsentModal', '@ProductAnalyticsConsentModalAccept'],
}, async ({
    browser,
    SalesChannelBaseConfig,
    AdminApiContext,
    InstanceMeta,
    TestDataService,
}) => {
    const { page, adminDashboard } = await createStableAdminPage(browser, SalesChannelBaseConfig, AdminApiContext);
    const adminOrderListing = new AdminPageObjects.OrderListing(page, InstanceMeta);
    const adminOrderDetail = new AdminPageObjects.OrderDetail(page, InstanceMeta);

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

    const product = await TestDataService.createBasicProduct();
    const customer = await TestDataService.createCustomer();
    const order = await TestDataService.createOrder([{ product: product, quantity: 1 }], customer);

    await test.step('Set consent for product analytics', async () => {
        await prepareProductAnalyticsConsentModal(page, AdminApiContext);
        await expect(page.locator('.sw-settings-usage-data-consent-modal__content')).toBeVisible();

        const backendDataConsentPromise = waitForConsentResponse(
            page,
            'accept',
            'backend_data',
        );
        const productAnalyticsConsentPromise = waitForConsentResponse(
            page,
            'accept',
            'product_analytics',
        );

        await removeSymfonyDebugToolbar(page);
        await page.getByRole('button', { name: ALLOW_ALL_BUTTON }).click();

        const backendDataResponse = await backendDataConsentPromise;
        const productAnalyticsResponse = await productAnalyticsConsentPromise;

        expect(backendDataResponse.ok()).toBeTruthy();
        expect(productAnalyticsResponse.ok()).toBeTruthy();
        await expect(page.locator('.sw-settings-usage-data-consent-modal__content')).not.toBeVisible();
    });

    await test.step('Intercept all the API calls to product analytics', async () => {
        await page.route(PRODUCT_ANALYTICS_ENDPOINT, requestHandler);
    });

    await test.step('Navigate via link to order page from dashboard', async () => {
        await adminDashboard.adminMenuOrder.click();
        await adminDashboard.adminMenuOrderOverview.click();
        await expect(adminOrderListing.addOrderButton).toBeVisible();
        await expectCapturedEventCount(captured, 2);
    });

    await test.step('Navigate via link to detail order page', async () => {
        const orderRow = await adminOrderListing.getLineItemByOrderNumber(order.orderNumber);
        await expect(orderRow.orderNumberText).toBeVisible();
        await orderRow.orderNumberText.click();
        await expectCapturedEventCount(captured, 4);
    });

    await test.step('Navigate via button to save order', async () => {
        await expect(adminOrderDetail.saveButton).toBeVisible();
        await expect(adminOrderDetail.contextMenuButton).toBeVisible();
        await adminOrderDetail.saveButton.click();
        await expectCapturedEventCount(captured, 5);
        await expect(adminOrderDetail.contextMenuButton).toBeVisible();
    });

    await test.step('Navigate via page view to dashboard page', async () => {
        await page.evaluate((hashRoute) => {
            document.location = hashRoute;
        }, adminDashboard.url());
        await page.waitForURL((url) => url.hash.startsWith('/sw/dashboard/index') || url.hash.startsWith('#/sw/dashboard/index'));
        await expect(adminDashboard.adminMenuOrder).toBeVisible();
        await expectCapturedEventCount(captured, 6);
        // eslint-disable-next-line playwright/no-conditional-in-test
        if (!await isSaaSInstance(TestDataService.AdminApiClient)) {
            await expect(adminDashboard.welcomeHeadline).toBeVisible();
        }
    });

    await test.step('Validate captured requests for product analytics', async () => {

        const events = parseCapturedEvents(captured);
        expect(events).toHaveLength(6);

        const eventIds = events.map((event) => event.event_id);

        expect(eventIds).toHaveLength(6);
        expect(new Set(eventIds).size).toBe(eventIds.length);
        expect(eventIds[0]).toBeGreaterThan(0);
        expect(eventIds.every((eventId, index, ids) => index === 0 || eventId > ids[index - 1])).toBeTruthy();

        const eventTypes = events.map(e => e.event_type);
        expect(eventTypes).toEqual([
            'link_visited', // event_id 1
            'page_viewed',  // event_id 2
            'link_visited', // event_id 3
            'page_viewed',  // event_id 4
            'button_click', // event_id 5
            'page_viewed',  // event_id 6
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

    await page.close();
});

test('As a merchant, I want to make sure no admin events are sent when I do not consent.', {
    tag: ['@ProductAnalytics', '@ProductAnalyticsConsentModal', '@ProductAnalyticsConsentModalReject'],
}, async ({
    browser,
    SalesChannelBaseConfig,
    AdminApiContext,
    InstanceMeta,
}) => {
    const { page, adminDashboard } = await createStableAdminPage(browser, SalesChannelBaseConfig, AdminApiContext);
    const adminOrderListing = new AdminPageObjects.OrderListing(page, InstanceMeta);

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

    await page.route(PRODUCT_ANALYTICS_ENDPOINT, requestHandler);

    await test.step('Do not set consent for product analytics', async () => {
        await prepareProductAnalyticsConsentModal(page, AdminApiContext);
        await expect(page.locator('.sw-settings-usage-data-consent-modal__content')).toBeVisible();

        const backendDataConsentPromise = waitForConsentResponse(
            page,
            'revoke',
            'backend_data',
        );
        const productAnalyticsConsentPromise = waitForConsentResponse(
            page,
            'revoke',
            'product_analytics',
        );

        await removeSymfonyDebugToolbar(page);
        await page.getByRole('button', { name: REJECT_ALL_BUTTON }).click();

        const backendDataResponse = await backendDataConsentPromise;
        const productAnalyticsResponse = await productAnalyticsConsentPromise;

        expect(backendDataResponse.ok()).toBeTruthy();
        expect(productAnalyticsResponse.ok()).toBeTruthy();
        await expect(page.locator('.sw-settings-usage-data-consent-modal__content')).not.toBeVisible();
    });

    await test.step('Navigate via link to order page from dashboard', async () => {
        await adminDashboard.adminMenuOrder.click();
        await adminDashboard.adminMenuOrderOverview.click();
        await expect(adminOrderListing.addOrderButton).toBeVisible();
        await expect(adminDashboard.adminMenuOrderOverview).toBeVisible();
    });

    await test.step('Validate no captured requests for product analytics', async () => {
        await expect.poll(() => parseCapturedEvents(captured).length, { timeout: 1000 }).toBe(0);
    });

    await page.close();
});

function parseCapturedEvents(captured: CapturedRequest[]): AmplitudeEvent[] {
    const events: AmplitudeEvent[] = [];

    for (const c of captured) {
        if (!c.postData) continue;
        try {
            const parsed: AmplitudeRequestPayload = JSON.parse(c.postData);
            if (Array.isArray(parsed.events)) {
                events.push(
                    ...parsed.events.filter((event) => TRACKED_ADMIN_EVENT_TYPES.has(event.event_type)),
                );
            }
        } catch {
            // If not JSON, ignore for now
        }
    }

    return events;
}
