/**
 * @sw-package data-services
 */
import type { ConsentEventName, TrackableType } from 'src/core/consent/events';

/**
 * @private
 */
export type EventPayload = Record<string, TrackableType>;

/**
 * @private
 */
export interface TrackingClient {
    init(): void;
    track(eventName: string, eventPayload?: EventPayload): void;
    identify(userId: string, userProperties?: EventPayload): void;
    getUserId(): string | null;
    reset(): void;
    clearStorage(): void;
    flush(): void;
    isInitialized: boolean;
}

/**
 * @private
 */
export class GatewayClient implements TrackingClient {
    constructor(
        private readonly gateWayUrl: string,
        private readonly adapter: TrackingClient,
    ) {}

    init(): void {
        this.adapter.init();
    }

    get isInitialized() {
        return this.adapter.isInitialized;
    }

    track(eventName: string, eventPayload?: EventPayload): void {
        this.adapter.track(eventName, eventPayload);
    }
    identify(userId: string, userProperties?: EventPayload): void {
        this.adapter.identify(userId, userProperties);
    }
    getUserId(): string | null {
        return this.adapter.getUserId();
    }
    reset(): void {
        this.adapter.reset();
    }

    clearStorage(): void {
        this.adapter.clearStorage();
    }

    flush() {
        this.adapter.flush();
    }

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
