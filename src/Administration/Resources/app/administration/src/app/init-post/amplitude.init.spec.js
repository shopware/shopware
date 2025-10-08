import initAmplitude from './amplitude.init';
import { TelemetryEvent } from '../../core/telemetry/types';

jest.mock('@amplitude/analytics-browser', () => ({
    add: jest.fn(),
    init: jest.fn(),
    track: jest.fn(),
    setUserId: jest.fn(),
    setTransport: jest.fn(),
    flush: jest.fn(),
    reset: jest.fn(),
}));

describe('src/app/post-init/amplitude.init.ts', () => {
    const anything = { asymmetricMatch: () => true };

    let mockLoginService;
    let mockUserService;

    beforeEach(() => {
        mockLoginService = {
            addOnLoginListener: jest.fn(),
            addOnLogoutListener: jest.fn(),
        };

        mockUserService = {
            getUser: jest.fn(),
        };

        Shopware.Service = jest.fn((serviceName) => {
            if (serviceName === 'loginService') {
                return mockLoginService;
            }
            if (serviceName === 'userService') {
                return mockUserService;
            }
            return undefined;
        });

        global.Shopware = {
            ...global.Shopware,
            Service: Shopware.Service,
        };

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
            expect(track).toHaveBeenCalledWith(trackedData.eventName, trackedData.properties, anything);
        });
    });

    describe('user identification', () => {
        const testShopId = 'knneBsx7LiKySnUq';
        const testUserId = '8b8ebef4-7fa3-4844-ab7e-120463ea558b';
        let originalShopId = null;

        beforeEach(() => {
            jest.clearAllMocks();

            mockUserService.getUser.mockResolvedValue({
                data: {
                    id: testUserId,
                    username: 'test-user',
                },
            });

            originalShopId = Shopware.Store.get('context').app.config.shopId;
            Shopware.Store.get('context').app.config.shopId = testShopId;

            global.repositoryFactoryMock.responses.addResponse({
                method: 'Post',
                url: '/search/user',
                status: 200,
                response: {
                    data: [
                        {
                            id: testUserId,
                            attributes: {
                                username: 'test-user',
                            },
                        },
                    ],
                },
            });
        });

        afterEach(() => {
            if (originalShopId !== null) {
                Shopware.Store.get('context').app.config.shopId = originalShopId;
                originalShopId = null;
            }
        });

        it('should set user ID when login listener is triggered', async () => {
            const amplitude = await import('@amplitude/analytics-browser');

            let loginCallback;
            mockLoginService.addOnLoginListener.mockImplementationOnce((callback) => {
                loginCallback = callback;
            });

            await initAmplitude();

            await loginCallback();

            expect(mockUserService.getUser).toHaveBeenCalled();
            expect(amplitude.setUserId).toHaveBeenCalledWith(expect.stringContaining(testUserId));
        });

        it('should set user ID in format "shopId:userId"', async () => {
            const amplitude = await import('@amplitude/analytics-browser');

            let loginCallback;
            mockLoginService.addOnLoginListener.mockImplementationOnce((callback) => {
                loginCallback = callback;
            });

            await initAmplitude();

            await loginCallback();

            expect(amplitude.setUserId).toHaveBeenCalledWith(`${testShopId}:${testUserId}`);
        });

        it('should update user ID when a different user logs in', async () => {
            const amplitude = await import('@amplitude/analytics-browser');

            mockUserService.getUser
                .mockResolvedValueOnce({
                    data: {
                        id: 'user-first',
                        username: 'admin',
                    },
                })
                .mockResolvedValueOnce({
                    data: {
                        id: 'user-second',
                        username: 'editor',
                    },
                });

            let loginCallback;
            mockLoginService.addOnLoginListener.mockImplementationOnce((callback) => {
                loginCallback = callback;
            });

            await initAmplitude();

            await loginCallback();

            expect(amplitude.setUserId).toHaveBeenCalledWith(expect.stringContaining('user-first'));

            amplitude.setUserId.mockClear();

            await loginCallback();

            expect(amplitude.setUserId).toHaveBeenCalledWith(expect.stringContaining('user-second'));
        });
    });

    describe('login and logout tracking', () => {
        it('should track Login event when login listener is triggered', async () => {
            const { track } = await import('@amplitude/analytics-browser');

            let loginCallback;
            mockLoginService.addOnLoginListener.mockImplementationOnce((callback) => {
                loginCallback = callback;
            });

            await initAmplitude();

            await loginCallback();

            expect(track).toHaveBeenCalledWith('Login', anything, anything);
        });

        it('should track Logout event when logout listener is triggered', async () => {
            const amplitude = await import('@amplitude/analytics-browser');

            let logoutCallback;
            mockLoginService.addOnLogoutListener.mockImplementationOnce((callback) => {
                logoutCallback = callback;
            });

            await initAmplitude();

            await logoutCallback();

            expect(amplitude.track).toHaveBeenCalledWith('Logout', anything, anything);
        });
    });
});
