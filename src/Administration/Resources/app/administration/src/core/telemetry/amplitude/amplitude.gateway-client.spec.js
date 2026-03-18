import createAnonymousGatewayClient from './amplitude.gateway-client';

describe('src/core/telemetry/amplitude/amplitude.gateway-client.ts', () => {
    beforeEach(() => {
        jest.clearAllMocks();
        global.fetch = jest.fn(() => Promise.resolve({ ok: true }));
    });

    it('sends anonymous consent events directly to the gateway', () => {
        const anonymousGatewayClient = createAnonymousGatewayClient('https://gateway.example');

        anonymousGatewayClient.track(
            'consent_modal_viewed',
            {
                option: ['product_analytics'],
            },
            1735689600000,
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
                                option: ['product_analytics'],
                            },
                            time: 1735689600000,
                        },
                    ],
                }),
            }),
        );
    });
});
