import { TelemetryEvent } from '../types';
import createTelemetryEventHandler from './telemetry-event-handler';

describe('src/core/telemetry/amplitude/telemetry-event-handler.ts', () => {
    let client;
    let pushTelemetryEventToAmplitude;

    beforeEach(() => {
        client = {
            track: jest.fn(),
            identify: jest.fn(),
            flush: jest.fn(),
        };

        Shopware.Store.get('context').app.config.shopId = 'shop-id-1';

        pushTelemetryEventToAmplitude = createTelemetryEventHandler(client);
    });

    it('identifies user', () => {
        pushTelemetryEventToAmplitude(
            new TelemetryEvent('identify', {
                userId: 'user-id-1',
                locale: 'en-GB',
                isAdmin: false,
            }),
        );

        expect(client.identify).toHaveBeenCalledWith('shop-id-1:user-id-1', {
            userId: 'user-id-1',
            locale: 'en-GB',
            isAdmin: false,
        });
    });

    it('tracks login event', () => {
        pushTelemetryEventToAmplitude(new TelemetryEvent('login', {}));

        expect(client.track).toHaveBeenCalledWith('login');
    });

    it('tracks logout event', () => {
        pushTelemetryEventToAmplitude(new TelemetryEvent('logout', {}));

        expect(client.track).toHaveBeenCalledWith('logout');
        expect(client.flush).toHaveBeenCalled();
    });

    it('normalizes non-string route names for page change tracking', () => {
        pushTelemetryEventToAmplitude(
            new TelemetryEvent('page_change', {
                from: {
                    name: Symbol('from-route'),
                    path: '/from',
                },
                to: {
                    name: null,
                    path: '/to',
                    fullPath: '/to?limit=10',
                },
            }),
        );

        expect(client.track).toHaveBeenCalledWith('page_viewed', {
            sw_route_from_name: 'Symbol(from-route)',
            sw_route_from_href: '/from',
            sw_route_to_name: null,
            sw_route_to_href: '/to',
            sw_route_to_query: 'limit=10',
        });
    });

    it('passes through programmatic telemetry event names unchanged', () => {
        pushTelemetryEventToAmplitude(
            new TelemetryEvent('programmatic', {
                eventName: 'page_viewed',
            }),
        );

        expect(client.track).toHaveBeenCalledWith('page_viewed', {
            eventName: 'page_viewed',
        });
    });
});
