/**
 * @sw-package framework
 */
import type { TrackableType } from 'src/core/consent/events';

type TrackClient = {
    track: (eventName: string, eventProperties: Record<string, TrackableType>, time: number) => void;
};

type GatewayEvent = {
    event_type: string;
    event_properties?: Record<string, TrackableType>;
    time: number;
};

/**
 * @private
 */
export default function createAnonymousGatewayClient(analyticsGatewayUrl: string): TrackClient {
    return {
        track(eventName, eventProperties, time) {
            void postGatewayEvents(`${analyticsGatewayUrl}/event/anonymous`, [
                {
                    event_type: eventName,
                    event_properties: eventProperties,
                    time,
                },
            ]);
        },
    };
}

async function postGatewayEvents(url: string, events: GatewayEvent[]): Promise<void> {
    try {
        await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'omit',
            keepalive: true,
            body: JSON.stringify({ events }),
        });
    } catch {
        // best-effort anonymous and privacy requests must not affect the admin runtime
    }
}
