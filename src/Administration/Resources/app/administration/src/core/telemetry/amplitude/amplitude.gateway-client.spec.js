import { createAnonymousGatewayClient, createDeleteUserGateWayClient } from './amplitude.gateway-client';

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

    it('sends delete requests directly to the gateway', () => {
        const anonymousGatewayClient = createDeleteUserGateWayClient('https://gateway.example');

        anonymousGatewayClient.track(
            'delete_user',
            {
                shop_id: 'shop-id',
                user_id: 'user-id',
                amplitude_user_id: `shop-id:user-id`,
            },
            1735689600000,
        );

        expect(global.fetch).toHaveBeenCalledWith(
            'https://gateway.example/delete-user',
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
                            event_type: 'delete_user',
                            event_properties: {
                                shop_id: 'shop-id',
                                user_id: 'user-id',
                                amplitude_user_id: `shop-id:user-id`,
                            },
                            time: 1735689600000,
                        },
                    ],
                }),
            }),
        );
    });
});
