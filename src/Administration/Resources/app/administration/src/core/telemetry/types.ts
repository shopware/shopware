/**
 * @sw-package framework
 */
import { type RouteLocation } from 'vue-router';

type TrackableType = string | string[] | number | boolean | null;

type AnalyticsEvents = {
    user_interaction: {
        target: HTMLElement;
        originalEvent: Event;
    };
    page_change: {
        from: RouteLocation;
        to: RouteLocation;
    };
    programmatic: {
        [key: string]: TrackableType;
    };
    identify: {
        userId: string | null;
        locale: string | null;
        isAdmin: boolean | null;
    };
    reset: object;
};

type EventTypes = keyof AnalyticsEvents;
type EventPayload<N extends EventTypes> = AnalyticsEvents[N];

class TelemetryEvent<N extends EventTypes> {
    public readonly timestamp: Date;

    constructor(
        public readonly eventType: N,
        public readonly eventData: AnalyticsEvents[N],
    ) {
        this.timestamp = new Date();
    }
}

type ElementQuery = (mutations: MutationRecord[]) => Element[];

type Config = {
    queries: ElementQuery[];
};

/** @private */
export { TelemetryEvent, type TrackableType, type EventTypes, type EventPayload, type ElementQuery, type Config };
