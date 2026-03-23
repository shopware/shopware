import { TelemetryEvent } from '../types';
import createTelemetryEventHandler from './telemetry-event-handler';

describe('src/core/telemetry/amplitude/telemetry-event-handler.ts', () => {
    let client;
    let pushTelemetryEventToAmplitude;

    beforeEach(() => {
        client = {
            track: jest.fn(),
            getUserId: jest.fn(() => undefined),
            identify: jest.fn(),
            reset: jest.fn(),
        };

        Shopware.Store.get('context').app.config.shopId = 'shop-id-1';

        pushTelemetryEventToAmplitude = createTelemetryEventHandler(client);
    });

    it('tracks login only when identify changes user id', () => {
        client.getUserId.mockReturnValue(undefined);

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
        expect(client.track).toHaveBeenCalledWith('login');

        client.track.mockClear();
        client.getUserId.mockReturnValue('shop-id-1:user-id-1');

        pushTelemetryEventToAmplitude(
            new TelemetryEvent('identify', {
                userId: 'user-id-1',
                locale: null,
                isAdmin: null,
            }),
        );

        expect(client.track).not.toHaveBeenCalled();
    });

    it('tracks logout and flushes/resets immediately', () => {
        pushTelemetryEventToAmplitude(new TelemetryEvent('reset', {}));

        expect(client.track).toHaveBeenCalledWith('logout');
        expect(client.reset).not.toHaveBeenCalled();
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
