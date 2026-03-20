/**
 * @sw-package data-services
 */
import type { ConsentEventName, TrackableType } from 'src/core/consent/events';

/**
 * @private
 */
export class GatewayClient {
    constructor(private readonly gateWayUrl: string) {}

    trackConsentMetric(metric: ConsentEventName, eventProperties: Record<string, TrackableType>, time: number) {
        void this.sendJsonRequest(`${this.gateWayUrl}/v1/event/anonymous`, {
            events: [
                {
                    event_type: metric,
                    event_properties: eventProperties,
                    time,
                },
            ],
        });
    }

    deleteUser(shopId: string, userId: string) {
        void this.sendJsonRequest(`${this.gateWayUrl}/v1/delete-user`, {
            shop_id: shopId,
            user_id: userId,
        });
    }

    private async sendJsonRequest(url: string, json: unknown) {
        try {
            await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                credentials: 'omit',
                keepalive: true,
                body: JSON.stringify(json),
            });
        } catch {
            // best-effort anonymous and privacy requests must not affect the admin runtime
        }
    }
}
