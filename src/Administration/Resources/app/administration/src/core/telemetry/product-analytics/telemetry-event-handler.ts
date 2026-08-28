/**
 * @sw-package framework
 */
import { string } from '../../service/util.service';
import type { EventTypes, TelemetryEvent, TrackableType } from '../types';
import type { TrackingClient } from './gateway-client';

type TelemetryEventHandlers = {
    [N in EventTypes]?: (event: TelemetryEvent<N>) => void;
};

/**
 * @private
 */
export default function createTelemetryEventHandler(
    client: TrackingClient,
): (telemetryEvent: TelemetryEvent<EventTypes>) => void {
    const telemetryEventHandlers: TelemetryEventHandlers = {
        page_change: (event) => {
            client.track('page_viewed', {
                source: 'admin',
                sw_route_from_name: normalizeRouteName(event.eventData.from.name),
                sw_route_from_href: event.eventData.from.path,
                sw_route_to_name: normalizeRouteName(event.eventData.to.name),
                sw_route_to_href: event.eventData.to.path,
                sw_route_to_query: event.eventData.to.fullPath.split('?')[1],
            });
        },
        identify: (event) => {
            if (event.eventData.userId) {
                client.identify(event.eventData.userId);
            }
        },
        login: () => {
            client.track('login', { source: 'admin' });
        },
        logout: () => {
            client.track('logout', { source: 'admin' });
            void client.flush();
        },
        user_interaction: (event) => {
            const { target, originalEvent } = event.eventData;

            const eventProperties: Record<string, TrackableType> = {};

            let eventName = string.snakeCase(`${target.tagName} ${originalEvent.type}`);

            if (target.tagName === 'A') {
                eventName = 'link_visited';

                eventProperties.sw_link_href = target.getAttribute('href') ?? '';
                eventProperties.sw_link_type = target.getAttribute('target') === '_blank' ? 'external' : 'internal';
            }

            target.getAttributeNames().forEach((attributeName) => {
                if (attributeName.startsWith('data-analytics-')) {
                    const propertyName = string.snakeCase(attributeName.replace('data-analytics-', 'sw_element_'));
                    eventProperties[propertyName] = target.getAttribute(attributeName);
                }
            });

            if (originalEvent instanceof MouseEvent) {
                eventProperties.sw_pointer_x = originalEvent.clientX;
                eventProperties.sw_pointer_y = originalEvent.clientY;
                eventProperties.sw_pointer_button = originalEvent.buttons;
            }

            client.track(eventName, { source: 'admin', ...eventProperties });
        },
        programmatic: (event) => {
            const { eventName, ...properties } = event.eventData;
            client.track(eventName, { source: 'admin', ...properties });
        },
    };

    return (telemetryEvent: TelemetryEvent<EventTypes>) => {
        const handler = telemetryEventHandlers[telemetryEvent.eventType] as
            | ((event: TelemetryEvent<EventTypes>) => void)
            | undefined;

        handler?.(telemetryEvent);
    };
}

function normalizeRouteName(routeName: unknown): string | null {
    if (typeof routeName === 'string') {
        return routeName;
    }

    if (typeof routeName === 'symbol') {
        return routeName.toString();
    }

    return null;
}
