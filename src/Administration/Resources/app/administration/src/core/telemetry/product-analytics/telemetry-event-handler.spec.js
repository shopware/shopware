import { TelemetryEvent } from '../types';
import createTelemetryEventHandler from './telemetry-event-handler';

describe('src/core/telemetry/product-analytics/telemetry-event-handler.ts', () => {
    let client;
    let pushTelemetryEventToGateway;

    beforeEach(() => {
        client = {
            track: jest.fn(),
            identify: jest.fn(),
            flush: jest.fn(),
        };

        pushTelemetryEventToGateway = createTelemetryEventHandler(client);
    });

    it('identifies user', () => {
        pushTelemetryEventToGateway(
            new TelemetryEvent('identify', {
                userId: 'user-id-1',
                locale: 'en-GB',
                isAdmin: false,
            }),
        );

        expect(client.identify).toHaveBeenCalledWith('user-id-1');
    });

    it('tracks login event', () => {
        pushTelemetryEventToGateway(new TelemetryEvent('login', {}));

        expect(client.track).toHaveBeenCalledWith('login', { source: 'admin' });
    });

    it('tracks logout event', () => {
        pushTelemetryEventToGateway(new TelemetryEvent('logout', {}));

        expect(client.track).toHaveBeenCalledWith('logout', { source: 'admin' });
        expect(client.flush).toHaveBeenCalled();
    });

    it('normalizes non-string route names for page change tracking', () => {
        pushTelemetryEventToGateway(
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
            source: 'admin',
            sw_route_from_name: 'Symbol(from-route)',
            sw_route_from_href: '/from',
            sw_route_to_name: null,
            sw_route_to_href: '/to',
            sw_route_to_query: 'limit=10',
        });
    });

    it('passes through programmatic telemetry event names unchanged', () => {
        pushTelemetryEventToGateway(
            new TelemetryEvent('programmatic', {
                eventName: 'page_viewed',
            }),
        );

        expect(client.track).toHaveBeenCalledWith('page_viewed', { source: 'admin' });
    });
});
