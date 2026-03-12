import { CookieStorage } from 'cookie-storage';
import initAmplitude from './amplitude.init';
import { TelemetryEvent } from '../../core/telemetry/types';
import { ConsentEvent } from '../../core/consent/events';
import useConsentStore from '../../core/consent/consent.store';

const mockDeleteUserAmplitudeClient = {
    init: jest.fn(),
    track: jest.fn(),
    flush: jest.fn(),
};
const amplitudeCookieName = 'AMP_placeholde';
const amplitudeMarketingCookieName = 'AMP_MKTG_placeholde';

jest.mock('@amplitude/analytics-browser', () => ({
    createInstance: jest.fn(() => mockDeleteUserAmplitudeClient),
    add: jest.fn(),
    init: jest.fn(),
    track: jest.fn(),
    setUserId: jest.fn(),
    getUserId: jest.fn(),
    setOptOut: jest.fn(),
    setTransport: jest.fn(),
    flush: jest.fn(),
    reset: jest.fn(),
}));

describe('src/app/post-init/amplitude.init.ts', () => {
    let mockLoginService;
    let storage;
    const testShopId = 'knneBsx7LiKySnUq';
    const testUserId = '8b8ebef4-7fa3-4844-ab7e-120463ea558b';

    beforeEach(() => {
        jest.clearAllMocks();
        Shopware.Utils.EventBus.all?.clear();
        const { createInstance } = jest.requireMock('@amplitude/analytics-browser');
        createInstance.mockReset();
        createInstance.mockImplementation(() => mockDeleteUserAmplitudeClient);
        mockDeleteUserAmplitudeClient.init.mockClear();
        mockDeleteUserAmplitudeClient.track.mockClear();
        mockDeleteUserAmplitudeClient.flush.mockClear();
        global.fetch = jest.fn(() => Promise.resolve({ ok: true }));
        document.cookie = `${amplitudeCookieName}=test-value`;
        document.cookie = `${amplitudeMarketingCookieName}=test-value`;
        storage = new CookieStorage({
            path: '/',
            domain: null,
            secure: false,
            sameSite: 'Lax',
        });

        mockLoginService = {
            addOnLogoutListener: jest.fn(),
            getStorage: jest.fn(() => storage),
        };

        Shopware.Service = jest.fn((serviceName) => {
            if (serviceName === 'loginService') {
                return mockLoginService;
            }
            return undefined;
        });

        global.Shopware = {
            ...global.Shopware,
            Service: Shopware.Service,
        };

        Shopware.Store.get('context').app.analyticsGatewayUrl = 'https://gateway.example';
        Shopware.Store.get('context').app.config.shopId = testShopId;
        Shopware.Store.get('session').currentUser = {
            id: testUserId,
        };
        useConsentStore().consents = {
            product_analytics: {
                name: 'product_analytics',
                status: 'accepted',
            },
        };
        Shopware.Utils.EventBus.all?.clear();

        global.repositoryFactoryMock.responses.addResponse({
            method: 'Post',
            url: '/search/language',
            status: 200,
            response: {
                data: [
                    {
                        id: 'language-id',
                        attributes: {
                            name: 'English',
                        },
                    },
                ],
            },
        });
    });

    describe('initialization', () => {
        it('add enrichment plugin and calls initialization routine', async () => {
            const { init, add } = await import('@amplitude/analytics-browser');

            await initAmplitude();

            expect(add).toHaveBeenCalled();
            expect(add).toHaveBeenCalledWith(
                expect.objectContaining({
                    name: 'DefaultShopwareProperties',
                    execute: expect.any(Function),
                }),
            );

            expect(init).toHaveBeenCalled();
            expect(init).toHaveBeenCalledWith(
                expect.any(String),
                undefined,
                expect.objectContaining({
                    autocapture: false,
                    serverZone: 'EU',
                    appVersion: Shopware.Store.get('context').app.config.version,
                    trackingOptions: {
                        ipAddress: false,
                        language: false,
                        platform: false,
                    },
                    fetchRemoteConfig: false,
                }),
            );
        });

        it('does not initialize anonymous amplitude when gateway base url is missing', async () => {
            const { init } = await import('@amplitude/analytics-browser');
            Shopware.Store.get('context').app.analyticsGatewayUrl = null;

            await initAmplitude();

            expect(init).not.toHaveBeenCalled();
            expect(global.fetch).not.toHaveBeenCalled();
        });

        it('does not initialize amplitude without product analytics consent', async () => {
            const { init } = await import('@amplitude/analytics-browser');
            useConsentStore().consents.product_analytics.status = 'revoked';
            init.mockClear();

            await initAmplitude();

            expect(init).not.toHaveBeenCalled();
        });
    });

    describe('event handling', () => {
        it.each([
            [
                new TelemetryEvent('page_change', {
                    from: { name: 'sw.dashboard.index', path: '/sw/dashboard/index' },
                    to: {
                        name: 'sw.product.index',
                        path: '/sw/product/index',
                        fullPath: '/sw-product/index?order=asc&page=1&limit=50',
                    },
                }),
                {
                    eventName: 'Page Viewed',
                    properties: {
                        sw_route_from_name: 'sw.dashboard.index',
                        sw_route_from_href: '/sw/dashboard/index',
                        sw_route_to_name: 'sw.product.index',
                        sw_route_to_href: '/sw/product/index',
                        sw_route_to_query: 'order=asc&page=1&limit=50',
                    },
                },
            ],
            [
                new TelemetryEvent('user_interaction', {
                    target: (() => {
                        const fakeButton = document.createElement('button');
                        fakeButton.innerText = 'Save';
                        fakeButton.setAttribute('data-analytics-id', 'administration.sw-product.save');
                        fakeButton.setAttribute('data-analytics-product-name', 'nice product');

                        return fakeButton;
                    })(),
                    originalEvent: new MouseEvent('click', {
                        clientX: 150,
                        clientY: 75,
                        button: 2,
                    }),
                }),
                {
                    eventName: 'Button Click',
                    properties: {
                        sw_element_id: 'administration.sw-product.save',
                        sw_element_product_name: 'nice product',
                        sw_pointer_x: 150,
                        sw_pointer_y: 75,
                        sw_pointer_button: 0,
                    },
                },
            ],
            [
                new TelemetryEvent('user_interaction', {
                    target: (() => {
                        const fakeLink = document.createElement('a');
                        fakeLink.innerText = 'Read more';
                        fakeLink.setAttribute('href', 'https://example.com');
                        fakeLink.setAttribute('target', '_blank');

                        return fakeLink;
                    })(),
                    originalEvent: new Event('click'),
                }),
                {
                    eventName: 'Link Visited',
                    properties: {
                        sw_link_href: 'https://example.com',
                        sw_link_type: 'external',
                    },
                },
            ],
        ])('handles event', async (telemetryEvent, trackedData) => {
            const { track } = await import('@amplitude/analytics-browser');

            await initAmplitude();

            Shopware.Utils.EventBus.emit('telemetry', telemetryEvent);

            expect(track).toHaveBeenCalled();
            expect(track).toHaveBeenCalledWith(trackedData.eventName, trackedData.properties);
        });

        it('does not send telemetry events without product analytics consent', async () => {
            const { track } = await import('@amplitude/analytics-browser');
            useConsentStore().consents.product_analytics.status = 'revoked';

            await initAmplitude();
            track.mockClear();

            Shopware.Utils.EventBus.emit(
                'telemetry',
                new TelemetryEvent('page_change', {
                    from: { name: 'sw.dashboard.index', path: '/sw/dashboard/index' },
                    to: {
                        name: 'sw.product.index',
                        path: '/sw/product/index',
                        fullPath: '/sw-product/index?order=asc&page=1&limit=50',
                    },
                }),
            );

            expect(track).not.toHaveBeenCalled();
        });

        it('routes consent events to the anonymous gateway client', async () => {
            const { track } = await import('@amplitude/analytics-browser');

            await initAmplitude();

            Shopware.Utils.EventBus.emit(
                'consent',
                new ConsentEvent('consent_modal_viewed', {
                    option: [
                        'backend_data',
                        'user_tracking',
                    ],
                }),
            );

            expect(global.fetch).toHaveBeenCalledWith(
                'https://gateway.example/event/anonymous',
                expect.objectContaining({
                    method: 'POST',
                    credentials: 'omit',
                    keepalive: true,
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        events: [
                            {
                                event_type: 'consent_modal_viewed',
                                event_properties: {
                                    option: [
                                        'backend_data',
                                        'user_tracking',
                                    ],
                                },
                            },
                        ],
                    }),
                }),
            );
            expect(track).not.toHaveBeenCalledWith('consent_modal_viewed', expect.anything());
        });

        it('routes consent events even without product analytics consent', async () => {
            useConsentStore().consents.product_analytics.status = 'revoked';

            await initAmplitude();

            Shopware.Utils.EventBus.emit(
                'consent',
                new ConsentEvent('consent_modal_viewed', {
                    option: [
                        'backend_data',
                        'user_tracking',
                    ],
                }),
            );

            expect(global.fetch).toHaveBeenCalledWith(
                'https://gateway.example/event/anonymous',
                expect.objectContaining({
                    body: JSON.stringify({
                        events: [
                            {
                                event_type: 'consent_modal_viewed',
                                event_properties: {
                                    option: [
                                        'backend_data',
                                        'user_tracking',
                                    ],
                                },
                            },
                        ],
                    }),
                }),
            );
        });

        it('stops telemetry after consent is revoked during runtime', async () => {
            const { createInstance, reset, setOptOut } = await import('@amplitude/analytics-browser');
            const consentStore = useConsentStore();
            const eventBusOffSpy = jest.spyOn(Shopware.Utils.EventBus, 'off');

            await initAmplitude();

            eventBusOffSpy.mockClear();

            consentStore.$patch({
                consents: {
                    ...consentStore.consents,
                    product_analytics: {
                        ...consentStore.consents.product_analytics,
                        status: 'revoked',
                    },
                },
            });

            await flushPromises();

            expect(eventBusOffSpy).toHaveBeenCalledWith('telemetry', expect.any(Function));
            expect(createInstance).toHaveBeenCalledTimes(1);
            expect(mockDeleteUserAmplitudeClient.init).toHaveBeenCalledWith(
                expect.any(String),
                undefined,
                expect.objectContaining({
                    serverUrl: 'https://gateway.example/delete-user',
                }),
            );
            expect(mockDeleteUserAmplitudeClient.track).toHaveBeenCalledWith('delete_user', {
                shop_id: testShopId,
                user_id: testUserId,
                amplitude_user_id: `${testShopId}:${testUserId}`,
            });
            expect(mockDeleteUserAmplitudeClient.flush).toHaveBeenCalledTimes(1);
            expect(setOptOut).toHaveBeenCalledWith(true);
            expect(reset).toHaveBeenCalled();
            expect(document.cookie).not.toContain(`${amplitudeCookieName}=`);
            expect(document.cookie).not.toContain(`${amplitudeMarketingCookieName}=`);
        });

        it('starts telemetry when consent is accepted during runtime', async () => {
            const { track, init, setOptOut } = await import('@amplitude/analytics-browser');
            const consentStore = useConsentStore();
            const eventBusOnSpy = jest.spyOn(Shopware.Utils.EventBus, 'on');

            jest.useFakeTimers();
            consentStore.consents.product_analytics.status = 'revoked';

            await initAmplitude();

            expect(init).not.toHaveBeenCalled();
            eventBusOnSpy.mockClear();
            track.mockClear();

            consentStore.$patch({
                consents: {
                    ...consentStore.consents,
                    product_analytics: {
                        ...consentStore.consents.product_analytics,
                        status: 'accepted',
                    },
                },
            });

            await flushPromises();

            expect(init).not.toHaveBeenCalled();
            expect(eventBusOnSpy).not.toHaveBeenCalledWith('telemetry', expect.any(Function));

            jest.runOnlyPendingTimers();
            await flushPromises();

            expect(init).toHaveBeenCalledTimes(1);
            expect(eventBusOnSpy).toHaveBeenCalledWith('telemetry', expect.any(Function));
            expect(setOptOut).toHaveBeenCalledWith(false);

            Shopware.Utils.EventBus.emit(
                'telemetry',
                new TelemetryEvent('page_change', {
                    from: { name: 'sw.dashboard.index', path: '/sw/dashboard/index' },
                    to: {
                        name: 'sw.product.index',
                        path: '/sw/product/index',
                        fullPath: '/sw-product/index?order=asc&page=1&limit=50',
                    },
                }),
            );

            expect(track.mock.calls).toHaveLength(1);

            jest.useRealTimers();
        });

        it('does not start telemetry before the deferred runtime activation finishes', async () => {
            const { track } = await import('@amplitude/analytics-browser');
            const consentStore = useConsentStore();

            jest.useFakeTimers();
            consentStore.consents.product_analytics.status = 'revoked';

            await initAmplitude();
            track.mockClear();

            consentStore.$patch({
                consents: {
                    ...consentStore.consents,
                    product_analytics: {
                        ...consentStore.consents.product_analytics,
                        status: 'accepted',
                    },
                },
            });

            await flushPromises();

            Shopware.Utils.EventBus.emit(
                'telemetry',
                new TelemetryEvent('page_change', {
                    from: { name: 'sw.dashboard.index', path: '/sw/dashboard/index' },
                    to: {
                        name: 'sw.product.index',
                        path: '/sw/product/index',
                        fullPath: '/sw-product/index?order=asc&page=1&limit=50',
                    },
                }),
            );

            expect(track).not.toHaveBeenCalled();

            jest.runOnlyPendingTimers();
            await flushPromises();

            Shopware.Utils.EventBus.emit(
                'telemetry',
                new TelemetryEvent('page_change', {
                    from: { name: 'sw.dashboard.index', path: '/sw/dashboard/index' },
                    to: {
                        name: 'sw.product.index',
                        path: '/sw/product/index',
                        fullPath: '/sw-product/index?order=asc&page=1&limit=50',
                    },
                }),
            );

            expect(track.mock.calls).toHaveLength(1);

            jest.useRealTimers();
        });

        it('does not send consent events when gateway base url is missing', async () => {
            Shopware.Store.get('context').app.analyticsGatewayUrl = null;

            await initAmplitude();

            Shopware.Utils.EventBus.emit(
                'consent',
                new ConsentEvent('consent_modal_viewed', {
                    option: [
                        'backend_data',
                        'user_tracking',
                    ],
                }),
            );

            expect(global.fetch).not.toHaveBeenCalled();
        });
    });

    describe('user identification', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        it('should set user ID in format "shopId:userId"', async () => {
            const amplitude = await import('@amplitude/analytics-browser');

            await initAmplitude();

            const identifyEvent = new TelemetryEvent('identify', {
                userId: testUserId,
            });

            Shopware.Utils.EventBus.emit('telemetry', identifyEvent);

            expect(amplitude.setUserId).toHaveBeenCalledWith(`${testShopId}:${testUserId}`);
        });

        it('should update user ID when a different user identifies', async () => {
            const amplitude = await import('@amplitude/analytics-browser');

            await initAmplitude();

            const firstIdentifyEvent = new TelemetryEvent('identify', {
                userId: testUserId,
            });

            Shopware.Utils.EventBus.emit('telemetry', firstIdentifyEvent);

            expect(amplitude.setUserId).toHaveBeenCalledWith(`${testShopId}:${testUserId}`);

            amplitude.setUserId.mockClear();

            const anotherUserId = '48dad3c3-89b9-47a1-bf67-a1cd6fc68952';
            const secondIdentifyEvent = new TelemetryEvent('identify', {
                userId: anotherUserId,
            });

            Shopware.Utils.EventBus.emit('telemetry', secondIdentifyEvent);

            expect(amplitude.setUserId).toHaveBeenCalledWith(`${testShopId}:${anotherUserId}`);
        });
    });

    describe('login and logout tracking', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        it('should track Login event when a identify telemetry event with a different userId arrives', async () => {
            const amplitude = await import('@amplitude/analytics-browser');

            let amplitudeUserId = null;
            jest.spyOn(amplitude, 'setUserId').mockImplementation((userId) => {
                amplitudeUserId = userId;
            });
            jest.spyOn(amplitude, 'getUserId').mockImplementation(() => amplitudeUserId);

            await initAmplitude();

            let newUserId = 'newUserId-1';
            Shopware.Utils.EventBus.emit(
                'telemetry',
                new TelemetryEvent('identify', {
                    userId: newUserId,
                }),
            );
            expect(amplitude.track).toHaveBeenCalledWith('Login');

            newUserId = 'newUserId-2';
            Shopware.Utils.EventBus.emit(
                'telemetry',
                new TelemetryEvent('identify', {
                    userId: newUserId,
                }),
            );
            expect(amplitude.track).toHaveBeenCalledWith('Login');

            const sameUserId = newUserId;
            Shopware.Utils.EventBus.emit(
                'telemetry',
                new TelemetryEvent('identify', {
                    userId: sameUserId,
                }),
            );

            expect(amplitude.track).toHaveBeenCalledTimes(2);
        });

        it('should track Logout event when a reset telemetry event arrives', async () => {
            const amplitude = await import('@amplitude/analytics-browser');

            await initAmplitude();

            const resetEvent = new TelemetryEvent('reset', {});

            Shopware.Utils.EventBus.emit('telemetry', resetEvent);

            expect(amplitude.track).toHaveBeenCalledWith('Logout');
        });

        it('should flush telemetry amplitude on logout listener execution', async () => {
            const amplitude = await import('@amplitude/analytics-browser');
            jest.useFakeTimers();

            await initAmplitude();

            expect(mockLoginService.addOnLogoutListener).toHaveBeenCalledTimes(1);
            const logoutListener = mockLoginService.addOnLogoutListener.mock.calls[0][0];

            logoutListener();

            expect(amplitude.setTransport).toHaveBeenCalledWith('beacon');

            jest.runOnlyPendingTimers();

            expect(amplitude.flush).toHaveBeenCalledTimes(1);
            expect(amplitude.reset).toHaveBeenCalledTimes(1);

            jest.useRealTimers();
        });
    });
});
