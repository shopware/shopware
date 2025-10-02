/**
 * @sw-package framework
 */
import * as amplitude from '@amplitude/analytics-browser';
import { string } from 'src/core/service/util.service';
import { TelemetryEvent, type EventTypes } from '../../core/telemetry/types';

/**
 * @private
 */
export default async function (): Promise<void> {
    let defaultLanguageName = '';

    try {
        defaultLanguageName = await getDefaultLanguageName();
    } catch {
        defaultLanguageName = 'N/A';
    }

    amplitude.add({
        name: 'DefaultShopwareProperties',
        execute: (amplitudeEvent) => {
            const route = Shopware.Application.view?.router?.currentRoute
                ? {
                      sw_page_name: Shopware.Application.view.router.currentRoute.value.name,
                      sw_page_path: Shopware.Application.view.router.currentRoute.value.path,
                      sw_page_full_path: Shopware.Application.view.router.currentRoute.value.fullPath,
                  }
                : {};

            amplitudeEvent.event_properties = {
                ...amplitudeEvent.event_properties,
                sw_version: Shopware.Store.get('context').app.config.version,
                sw_shop_id: Shopware.Store.get('context').app.config.shopId,
                sw_user_agent: window.navigator.userAgent,
                sw_default_language: defaultLanguageName,
                sw_default_currency: Shopware.Context.app.systemCurrencyISOCode,
                sw_screen_width: window.screen.width,
                sw_screen_height: window.screen.height,
                sw_screen_orientation: window.screen.orientation.type.split('-')[0],
                ...route,
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
                sw_route_from_name: telemetryEvent.detail.eventData.from.name,
                sw_route_from_href: telemetryEvent.detail.eventData.from.path,
                sw_route_to_name: telemetryEvent.detail.eventData.to.name,
                sw_route_to_href: telemetryEvent.detail.eventData.to.path,
                sw_route_to_query: telemetryEvent.detail.eventData.to.fullPath.split('?')[1],
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
}

async function getDefaultLanguageName(): Promise<string> {
    const languageRepository = Shopware.Service('repositoryFactory').create('language');
    const defaultLanguage = await languageRepository.get(Shopware.Context.api.systemLanguageId!);

    return defaultLanguage!.name;
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
