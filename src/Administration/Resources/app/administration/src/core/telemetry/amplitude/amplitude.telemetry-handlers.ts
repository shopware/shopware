/**
 * @sw-package framework
 */
import { string } from 'src/core/service/util.service';
import type * as AmplitudeClient from '@amplitude/analytics-browser';
import type { EventTypes, TelemetryEvent, TrackableType } from 'src/core/telemetry/types';

type TelemetryEventHandlers = {
    [N in EventTypes]?: (event: TelemetryEvent<N>) => void;
};

/**
 * @private
 */
export default function createTelemetryEventHandler(
    amplitude: typeof AmplitudeClient,
): (telemetryEvent: TelemetryEvent<EventTypes>) => void {
    const telemetryEventHandlers: TelemetryEventHandlers = {
        page_change: (event) => {
            amplitude.track('page_viewed', {
                sw_route_from_name: normalizeRouteName(event.eventData.from.name),
                sw_route_from_href: event.eventData.from.path,
                sw_route_to_name: normalizeRouteName(event.eventData.to.name),
                sw_route_to_href: event.eventData.to.path,
                sw_route_to_query: event.eventData.to.fullPath.split('?')[1],
            });
        },
        identify: (event) => {
            const shopId = Shopware.Store.get('context').app.config.shopId;
            const newUserId = `${shopId}:${event.eventData.userId}`;

            const previousUserId = amplitude.getUserId();
            amplitude.setUserId(newUserId);
            // add more user properties via amplitude.identify(); ?

            if (newUserId && previousUserId !== newUserId) {
                amplitude.track('login');
            }
        },
        reset: () => {
            amplitude.track('logout');
            amplitude.flush();
            amplitude.reset();
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
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-call
                    const propertyName = string.snakeCase(attributeName.replace('data-analytics-', 'sw_element_'));
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                    eventProperties[propertyName] = target.getAttribute(attributeName);
                }
            });

            if (originalEvent instanceof MouseEvent) {
                eventProperties.sw_pointer_x = originalEvent.clientX;
                eventProperties.sw_pointer_y = originalEvent.clientY;
                eventProperties.sw_pointer_button = originalEvent.buttons;
            }

            amplitude.track(eventName, eventProperties);
        },
        programmatic: (event) => {
            amplitude.track(event.eventData.eventName, event.eventData);
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
