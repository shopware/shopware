import initAmplitude from './amplitude.init';
import { TelemetryEvent } from '../../core/telemetry/types';

jest.mock('@amplitude/analytics-browser', () => ({
    add: jest.fn(),
    init: jest.fn(),
    track: jest.fn(),
}));

describe('src/app/post-init/amplitude.init.ts', () => {
    beforeEach(() => {
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

            let amplitudeListener;
            jest.spyOn(Shopware.Telemetry, 'addListener').mockImplementationOnce((callback) => {
                amplitudeListener = callback;
            });

            await initAmplitude();

            amplitudeListener(telemetryEvent);

            expect(track).toHaveBeenCalled();
            expect(track).toHaveBeenCalledWith(trackedData.eventName, trackedData.properties);
        });
    });
});
