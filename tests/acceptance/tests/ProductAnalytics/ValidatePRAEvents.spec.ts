import { isSaaSInstance, test, expect, Page, Actor, AdminPageObjects, createNewAdminPageContext, loginToAdministration, User } from '@fixtures/AcceptanceTest';
import { parseCapturedRequests, setupConsentInterceptor,
    setupProductAnalyticsInterceptor, waitForEventCount,
} from '@helpers/productanalytics-helpers';
import { satisfies } from 'compare-versions';

const TRACKING_EVENT_ENDPOINT = 'event';
const CONSENTS_ENDPOINT = 'consents';

test.describe('Product Analytics - Validate events.',
    { tag: '@ProductAnalytics' }, () => {
    test('As a merchant, I want to make sure admin events are sent correctly.', { tag: '@ProductAnalytics' }, async ({
        TestDataService,
        browser,
        SalesChannelBaseConfig,
        InstanceMeta,
    }) => {

        test.skip(satisfies(InstanceMeta.version, '<6.7.9.0'), 'Product Analytics is only available since version 6.7.9.0');

        const { capturedTrackingEventRequests, trackingEventHandler } = setupProductAnalyticsInterceptor();

        const page: Page = await createNewAdminPageContext(browser, SalesChannelBaseConfig);

        const product = await TestDataService.createBasicProduct();
        const customer = await TestDataService.createCustomer();
        const order = await TestDataService.createOrder([{ product: product, quantity: 1 }], customer);

        const ShopAdmin = new Actor('Shop administrator', page, SalesChannelBaseConfig.adminUrl );
        const AdminDashboard = new AdminPageObjects['Dashboard'](page);
        const AdminOrderListing = new AdminPageObjects['OrderListing'](page, InstanceMeta);
        const AdminOrderDetail = new AdminPageObjects['OrderDetail'](page, InstanceMeta);

        await test.step('Intercept all API calls to product analytics', async () => {

            const { consentHandler } = setupConsentInterceptor({ backend_data: 'declined', product_analytics: 'accepted' });

            // Intercept event and event/anonymous requests
            await page.route(`**/${TRACKING_EVENT_ENDPOINT}**`, trackingEventHandler);
            await page.route(`**/${CONSENTS_ENDPOINT}`, consentHandler);
        });

        await test.step('Login to shopware administration', async () => {

            const user: User = await TestDataService.createUser();

            await loginToAdministration(
                page,
                user,
                TestDataService.AdminApiClient,
            );
        });

        await test.step('Navigate via link to order page from dashboard', async () => {

            await AdminDashboard.adminMenuOrder.click();
            await AdminDashboard.adminMenuOrderOverview.click();
            await ShopAdmin.expects(AdminOrderListing.addOrderButton).toBeVisible();
        });

        await test.step('Navigate via link to detail order page', async () => {

            const orderRow = await AdminOrderListing.getLineItemByOrderNumber(order.orderNumber);
            await ShopAdmin.expects(orderRow.orderNumberText).toBeVisible()
            await orderRow.orderNumberText.click();
        });

        await test.step('Navigate via button to save order', async () => {

            await ShopAdmin.expects(AdminOrderDetail.saveButton).toBeVisible();
            await ShopAdmin.expects(AdminOrderDetail.contextMenuButton).toBeVisible()
            await AdminOrderDetail.saveButton.click();
            await ShopAdmin.expects(AdminOrderDetail.contextMenuButton).toBeVisible()
        });

        await test.step('Navigate via page view to dashboard page', async () => {

            await ShopAdmin.goesTo(AdminDashboard.url());

            await ShopAdmin.expects(AdminDashboard.adminMenuOrder).toBeVisible();
            // eslint-disable-next-line playwright/no-conditional-in-test
            if (!await isSaaSInstance(TestDataService.AdminApiClient)) {
                await ShopAdmin.expects(AdminDashboard.welcomeHeadline).toBeVisible();
            }
        });

        await test.step('Validate captured requests for product analytics', async () => {

            // We expect 10 events in total, but they can be in multiple requests
            // Login > Page View (Dashboard) > Link Click (Order) >
            // Page View (Order listing) > Page View (Order listing with filters) >
            // Page View (Order listing with filters and grid filter null) >
            // Link Click (Order detail) > Page View (Order detail) >
            // Button Click (Save) > Page View (Dashboard)
            const requests = parseCapturedRequests(capturedTrackingEventRequests);
            expect(requests.length).toBeGreaterThanOrEqual(1);

            const getAnalyticsEvents = () =>
                parseCapturedRequests(capturedTrackingEventRequests).flatMap(request => request.events);

            await waitForEventCount(getAnalyticsEvents, 10);

            const events = getAnalyticsEvents();

            const loginEvents = events.filter(e => e.name === 'login');
            const pageViewed = events.filter(e => e.name === 'page_viewed');
            const linkVisited = events.filter(e => e.name === 'link_visited');
            const buttonClicked = events.filter(e => e.name === 'button_click');

            expect(loginEvents).toHaveLength(1);
            expect(pageViewed).toHaveLength(6);
            expect(linkVisited).toHaveLength(2);
            expect(buttonClicked).toHaveLength(1);

            const authenticatedRequests = requests.filter((request) => request.user?.id != null);

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

            expect(loginEvents[0].properties.sw_page_name).toBe('sw.dashboard.index');
            expect(loginEvents[0].properties.sw_page_path).toBe('/sw/dashboard/index');
            expect(loginEvents[0].properties.sw_page_full_path).toBe('/sw/dashboard/index');

            const pageViewedEvents = events.filter(e => e.name === 'page_viewed');

            expect(pageViewedEvents).toHaveLength(6);

            expect(pageViewedEvents).toEqual(
                expect.arrayContaining([
                    // initial dashboard
                    expect.objectContaining({
                        properties: {
                            source: 'admin',
                            sw_route_from_name: null,
                            sw_route_from_href: '/',
                            sw_route_to_name: 'sw.dashboard.index',
                            sw_route_to_href: '/sw/dashboard/index',
                            sw_page_name: 'sw.dashboard.index',
                            sw_page_path: '/sw/dashboard/index',
                            sw_page_full_path: '/sw/dashboard/index',
                        },
                    }),

                    // dashboard -> order index
                    expect.objectContaining({
                        properties: {
                            source: 'admin',
                            sw_route_from_name: 'sw.dashboard.index',
                            sw_route_from_href: '/sw/dashboard/index',
                            sw_route_to_name: 'sw.order.index',
                            sw_route_to_href: '/sw/order/index',
                            sw_page_name: 'sw.order.index',
                            sw_page_path: '/sw/order/index',
                            sw_page_full_path: '/sw/order/index',
                        },
                    }),

                    // order index (query #1)
                    expect.objectContaining({
                        properties: expect.objectContaining({
                            source: 'admin',
                            sw_route_from_name: 'sw.order.index',
                            sw_route_from_href: '/sw/order/index',
                            sw_route_to_name: 'sw.order.index',
                            sw_route_to_href: '/sw/order/index',
                            sw_page_name: 'sw.order.index',
                            sw_page_path: '/sw/order/index',
                            sw_page_full_path: expect.stringContaining('/sw/order/index?'),
                        }),
                    }),

                    // order index (query #2 with filter)
                    expect.objectContaining({
                        properties: expect.objectContaining({
                            source: 'admin',
                            sw_route_from_name: 'sw.order.index',
                            sw_route_from_href: '/sw/order/index',
                            sw_route_to_name: 'sw.order.index',
                            sw_route_to_href: '/sw/order/index',
                            sw_page_name: 'sw.order.index',
                            sw_page_path: '/sw/order/index',
                            sw_page_full_path: expect.stringContaining('grid.filter.order'),
                        }),
                    }),

                    // order index -> order detail
                    expect.objectContaining({
                        properties: {
                            source: 'admin',
                            sw_route_from_name: 'sw.order.index',
                            sw_route_from_href: '/sw/order/index',
                            sw_route_to_name: 'sw.order.detail.general',
                            sw_route_to_href: expect.stringContaining('/sw/order/detail/'),
                            sw_page_name: 'sw.order.detail.general',
                            sw_page_path: expect.stringContaining('/sw/order/detail/'),
                            sw_page_full_path: expect.stringContaining('/sw/order/detail/'),
                        },
                    }),

                    // back to dashboard
                    expect.objectContaining({
                        properties: {
                            source: 'admin',
                            sw_route_from_name: 'sw.order.detail.general',
                            sw_route_from_href: expect.stringContaining('/sw/order/detail/'),
                            sw_route_to_name: 'sw.dashboard.index',
                            sw_route_to_href: '/sw/dashboard/index',
                            sw_page_name: 'sw.dashboard.index',
                            sw_page_path: '/sw/dashboard/index',
                            sw_page_full_path: '/sw/dashboard/index',
                        },
                    }),
                ])
            );

            const linkVisitedEvents = events.filter(e => e.name === 'link_visited');

            expect(linkVisitedEvents).toHaveLength(2);

            expect(linkVisitedEvents).toEqual(
                expect.arrayContaining([
                    expect.objectContaining({
                        properties: expect.objectContaining({
                            source: 'admin',
                            sw_link_href: '#/sw/order/index',
                            sw_link_type: 'internal',
                            sw_page_name: 'sw.dashboard.index',
                            sw_page_path: '/sw/dashboard/index',
                            sw_page_full_path: '/sw/dashboard/index',
                        }),
                    }),

                    expect.objectContaining({
                        properties: expect.objectContaining({
                            source: 'admin',
                            sw_link_href: expect.stringContaining('#/sw/order/detail/'),
                            sw_link_type: 'internal',
                            sw_page_name: 'sw.order.index',
                            sw_page_path: '/sw/order/index',
                            sw_page_full_path: expect.stringContaining('/sw/order/index?'),
                        }),
                    }),
                ])
            );

            const buttonClickedEvents = events.filter(e => e.name === 'button_click');

            expect(buttonClickedEvents).toHaveLength(1);

            expect(buttonClickedEvents).toEqual(
                expect.arrayContaining([
                    expect.objectContaining({
                        properties: expect.objectContaining({
                            source: 'admin',
                            sw_element_id: 'sw-order-detail.save-edits',
                            sw_page_name: 'sw.order.detail.general',
                            sw_page_path: expect.stringContaining('/sw/order/detail/'),
                            sw_page_full_path: expect.stringContaining('/sw/order/detail/'),
                        }),
                    }),
                ])
            );
        });
    });
});
