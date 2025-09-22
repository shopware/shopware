/**
 * @sw-package framework
 */
import * as amplitude from '@amplitude/analytics-browser';
import { string } from 'src/core/service/util.service';
import { TelemetryEvent, type EventTypes } from '../../core/telemetry/types';

/**
 * @private
 */
export default function (): Promise<void> {
    amplitude.add({
        name: 'DefaultShopwareProperties',
        execute: (amplitudeEvent) => {
            amplitudeEvent.event_properties = {
                ...amplitudeEvent.event_properties,
                sw_version: Shopware.Store.get('context').app.config.version,
                sw_user_language: Shopware.Store.get('session').currentLocale,
                sw_user_is_admin: Shopware.Store.get('session').currentUser?.admin === true,
                sw_user_timezone: Shopware.Store.get('session').currentUser?.timeZone,
                sw_shop_id: Shopware.Store.get('context').app.config.shopId,
                sw_environment: Shopware.Store.get('context').app.environment,
            };
            return Promise.resolve(amplitudeEvent);
        },
    });

    // check for consent
    // identify user

    amplitude.init('a04bb926f471ce883bc219814fc9577', undefined, {
        autocapture: false,
        serverZone: 'EU',
        appVersion: Shopware.Store.get('context').app.config.version as string,
        trackingOptions: {
            ipAddress: false,
            language: false,
            platform: false,
        },
        // serverUrl: use proxy server url here, e.g. usage-data.shopware.io/product-analytics,
    });

    Shopware.Telemetry.addListener((telemetryEvent) => {
        if (!isTelemetryEvent(telemetryEvent)) {
            return;
        }

        if (isEventOfType('page_change', telemetryEvent)) {
            amplitude.track('Page Viewed', {
                sw_route_from: telemetryEvent.detail.eventData.from.name,
                href_route_from: telemetryEvent.detail.eventData.from.path,
                sw_route_to: telemetryEvent.detail.eventData.to.name,
                href_route_to: telemetryEvent.detail.eventData.to.path,
            });
            return;
        }

        if (isEventOfType('link_visited', telemetryEvent)) {
            amplitude.track('Link Visited', {
                href: telemetryEvent.detail.eventData.href,
                link_type: telemetryEvent.detail.eventData.linkType,
            });
            return;
        }

        if (isEventOfType('user_interaction', telemetryEvent)) {
            const target = telemetryEvent.detail.eventData.target;

            if (!(target instanceof Element)) {
                return;
            }

            const capitalizedTagName = string.capitalizeString(target.tagName);
            const eventName = string.capitalizeString(telemetryEvent.detail.eventData.originalEvent.type);

            amplitude.track(`${capitalizedTagName} ${eventName}`, {
                sw_button_text: target.textContent,
                sw_button_action: target.getAttribute('data-product-analytics-button-action') ?? '',
                sw_button_id: target.getAttribute('data-product-analytics-button-id') ?? target.id ?? '',
            });
        }
    });

    return Promise.resolve();
}

function isTelemetryEvent(telemetryEvent: Event): telemetryEvent is TelemetryEvent<EventTypes> {
    return telemetryEvent instanceof TelemetryEvent;
}

function isEventOfType<N extends EventTypes>(
    eventType: N,
    telemetryEvent: TelemetryEvent<EventTypes>,
): telemetryEvent is TelemetryEvent<N> {
    return telemetryEvent.detail.eventType === eventType;
}
