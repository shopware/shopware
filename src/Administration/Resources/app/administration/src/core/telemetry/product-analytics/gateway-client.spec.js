import { GatewayClient } from './gateway-client';

describe('src/core/telemetry/product-analytics/gateway-client', () => {
    let fetchMock;
    let trackerMock;
    let client;

    beforeEach(() => {
        // Default fetch mock resolves successfully
        fetchMock = jest.fn().mockResolvedValue({});
        global.fetch = fetchMock;

        trackerMock = { track: jest.fn() };
        client = new GatewayClient('https://gw.test', trackerMock);
    });

    afterEach(() => {
        jest.resetAllMocks();
        // Clean up global.fetch to avoid leaking into other tests
        try {
            delete global.fetch;
        } catch {
            // ignore
        }
    });

    it('sends consent metric to the anonymous event endpoint with correct payload', async () => {
        const time = Date.now();
        const properties = { foo: 'bar' };

        await client.trackConsentMetric('consent_given', properties, time);

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock).toHaveBeenCalledWith(
            'https://gw.test/v1/event/anonymous',
            expect.objectContaining({
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                credentials: 'omit',
                keepalive: true,
                body: JSON.stringify({
                    events: [
                        {
                            event_type: 'consent_given',
                            event_properties: properties,
                            time,
                        },
                    ],
                }),
            }),
        );
    });

    it('sends deleteUser request with shop and user ids', async () => {
        await client.deleteUser('shop-1', 'user-1');

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock).toHaveBeenCalledWith(
            'https://gw.test/v1/delete-user',
            expect.objectContaining({
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                credentials: 'omit',
                keepalive: true,
                body: JSON.stringify({ shop_id: 'shop-1', user_id: 'user-1' }),
            }),
        );
    });
});
