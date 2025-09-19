import initAmplitude from './amplitude.init';
import { TelemetryEvent } from '../../core/telemetry/types';

jest.mock('@amplitude/analytics-browser', () => ({
    add: jest.fn(),
    init: jest.fn(),
    track: jest.fn(),
}));

describe('src/app/post-init/amplitude.init.ts', () => {
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
                }),
            );
        });
    });

    describe('event handling', () => {
        it.each([
            [
                new TelemetryEvent('page_change', {
                    from: { name: 'sw.dashboard.index', path: '/sw/dashboard/index' },
                    to: { name: 'sw.product.index', path: '/sw/product/index' },
                }),
                {
                    eventName: '[Product Analytics] Page Viewed',
                    properties: {
                        sw_route_from: 'sw.dashboard.index',
                        href_route_from: '/sw/dashboard/index',
                        sw_route_to: 'sw.product.index',
                        href_route_to: '/sw/product/index',
                    },
                },
            ],
            [
                new TelemetryEvent('link_visited', {
                    href: 'https://example.com',
                    linkType: 'external',
                }),
                {
                    eventName: '[Product Analytics] Link Visited',
                    properties: {
                        href: 'https://example.com',
                        link_type: 'external',
                    },
                },
            ],
            [
                new TelemetryEvent('user_interaction', {
                    target: (() => {
                        const fakeButton = document.createElement('button');
                        fakeButton.textContent = 'Save';
                        fakeButton.setAttribute('data-product-analytics-button-action', 'save');
                        fakeButton.setAttribute('data-product-analytics-button-id', 'administration.sw-product.save');

                        return fakeButton;
                    })(),
                    originalEvent: new Event('click'),
                }),
                {
                    eventName: '[Product Analytics] Button Clicked',
                    properties: {
                        sw_button_text: 'Save',
                        sw_button_action: 'save',
                        sw_button_id: 'administration.sw-product.save',
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
