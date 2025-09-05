/**
 * @sw-package framework
 */
import { type RouteLocation } from 'vue-router';

type TrackableType = string | string[] | number | boolean;

type AnalyticsEvents = {
    user_interaction: {
        target: EventTarget | null;
        originalEvent: Event;
    };
    page_change: {
        from: RouteLocation;
        to: RouteLocation;
    };
    programmatic: {
        [key: string]: TrackableType;
    };
    link_visited: {
        href: string;
        linkType: 'internal' | 'external';
    };
    identify: {
        userId: string;
        deviceId: string;
        locale: string;
        permissions: string[];
    };
};

type EventTypes = keyof AnalyticsEvents;
type EventPayload<N extends EventTypes> = AnalyticsEvents[N];
type EventData<N extends EventTypes> = {
    eventType: N;
    eventData: EventPayload<N>;
    timestamp: Date;
};

class TelemetryEvent<N extends EventTypes> extends CustomEvent<EventData<N>> {
    constructor(eventType: N, eventData: AnalyticsEvents[N]) {
        super('telemetry', {
            detail: {
                eventType,
                eventData,
                timestamp: new Date(),
            },
        });
    }
}

/** @private */
export { TelemetryEvent, type EventTypes, type EventPayload };
