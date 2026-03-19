import { TelemetryEvent } from 'src/core/telemetry/types';
import createTelemetryEventHandler from './amplitude.telemetry-handlers';

describe('src/core/telemetry/amplitude/amplitude.telemetry-handlers.ts', () => {
    let amplitude;
    let pushTelemetryEventToAmplitude;

    beforeEach(() => {
        amplitude = {
            track: jest.fn(),
            getUserId: jest.fn(() => undefined),
            setUserId: jest.fn(),
            flush: jest.fn(),
            reset: jest.fn(),
        };

        Shopware.Store.get('context').app.config.shopId = 'shop-id-1';

        pushTelemetryEventToAmplitude = createTelemetryEventHandler(amplitude);
    });

    it('tracks login only when identify changes user id', () => {
        amplitude.getUserId.mockReturnValue(undefined);

        pushTelemetryEventToAmplitude(
            new TelemetryEvent('identify', {
                userId: 'user-id-1',
                locale: null,
                isAdmin: null,
            }),
        );

        expect(amplitude.setUserId).toHaveBeenCalledWith('shop-id-1:user-id-1');
        expect(amplitude.track).toHaveBeenCalledWith('login');

        amplitude.track.mockClear();
        amplitude.getUserId.mockReturnValue('shop-id-1:user-id-1');

        pushTelemetryEventToAmplitude(
            new TelemetryEvent('identify', {
                userId: 'user-id-1',
                locale: null,
                isAdmin: null,
            }),
        );

        expect(amplitude.track).not.toHaveBeenCalled();
    });

    it('tracks logout and flushes/resets immediately', () => {
        pushTelemetryEventToAmplitude(new TelemetryEvent('reset', {}));

        expect(amplitude.track).toHaveBeenCalledWith('logout');
        expect(amplitude.flush).not.toHaveBeenCalled();
        expect(amplitude.reset).not.toHaveBeenCalled();
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

        expect(amplitude.track).toHaveBeenCalledWith('page_viewed', {
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

        expect(amplitude.track).toHaveBeenCalledWith('page_viewed', {
            eventName: 'page_viewed',
        });
    });
});
