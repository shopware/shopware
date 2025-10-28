/**
 * @sw-package framework
 */
import * as amplitude from '@amplitude/analytics-browser';
import type { BaseEvent, TransportType, Transport, Payload, Response } from '@amplitude/analytics-core';
import { string } from 'src/core/service/util.service';
import { SendBeaconTransport } from '@amplitude/analytics-browser/lib/esm/transports/send-beacon';
import { BaseTransport } from '@amplitude/analytics-core';
import type { TelemetryEvent, EventTypes, TrackableType } from '../../core/telemetry/types';

/**
 * @private
 */
export default async function (): Promise<void> {
    Shopware.Service('loginService').addOnLogoutListener(() => {
        amplitude.setTransport('beacon');
    });

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
                sw_app_url: Shopware.Store.get('context').app.config.appUrl,
                sw_browser_url: window.location.origin,
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

    const amp = amplitude.init('placeholder-apikey', undefined, {
        autocapture: false,
        serverZone: 'EU',
        appVersion: Shopware.Store.get('context').app.config.version as string,
        trackingOptions: {
            ipAddress: false,
            language: false,
            platform: false,
        },
        fetchRemoteConfig: false,
        serverUrl: 'http://localhost:8080/event',
        transportProvider: (transport?: TransportType): Transport => {
            if (transport === 'beacon') {
                return new SendBeaconTransport();
            }

            return new class extends BaseTransport implements Transport {
                async send(serverUrl: string, payload: Payload): Promise<Response | null> {
                    const token = await Shopware.Service('analyticsService').getToken()

                    const options: RequestInit = {
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorisation': `Bearer ${token.token}`,
                            Accept: '*/*',
                        },
                        body: JSON.stringify(payload),
                        method: 'POST',
                    };
                    const response = await fetch(serverUrl, options);
                    const responseText = await response.text();
                    try {
                        // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
                        return this.buildResponse(JSON.parse(responseText));
                    } catch {
                        return this.buildResponse({ code: response.status });
                    }
                }
            }
        },
    });



    // eslint-disable-next-line listeners/no-missing-remove-event-listener
    Shopware.Utils.EventBus.on('telemetry', pushTelemetryEventToAmplitude);
}

function pushTelemetryEventToAmplitude(telemetryEvent: TelemetryEvent<EventTypes>) {
    if (isEventOfType('page_change', telemetryEvent)) {
        amplitude.track('Page Viewed', {
            sw_route_from_name: telemetryEvent.eventData.from.name,
            sw_route_from_href: telemetryEvent.eventData.from.path,
            sw_route_to_name: telemetryEvent.eventData.to.name,
            sw_route_to_href: telemetryEvent.eventData.to.path,
            sw_route_to_query: telemetryEvent.eventData.to.fullPath.split('?')[1],
        });
        return;
    }

    if (isEventOfType('identify', telemetryEvent)) {
        const shopId = Shopware.Store.get('context').app.config.shopId;
        const newUserId = `${shopId}:${telemetryEvent.eventData.userId}`;

        const previousUserId = amplitude.getUserId();
        amplitude.setUserId(newUserId);
        // add more user properties via amplitude.identify(); ?

        if (newUserId && previousUserId !== newUserId) {
            amplitude.track('Login');
        }

        return;
    }

    if (isEventOfType('reset', telemetryEvent)) {
        amplitude.track('Logout');

        // we need a timeout if we want to include the click on the logout button
        setTimeout(() => {
            amplitude.flush();
            amplitude.reset();
        }, 0);

        return;
    }

    if (isEventOfType('user_interaction', telemetryEvent)) {
        const { target, originalEvent } = telemetryEvent.eventData;

        const eventProperties: Record<string, TrackableType> = {};

        const capitalizedTagName = string.capitalizeString(target.tagName);
        const capitalizedEventName = string.capitalizeString(originalEvent.type);

        let eventName = `${capitalizedTagName} ${capitalizedEventName}`;

        if (target.tagName === 'A') {
            eventName = 'Link Visited';

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

        amplitude.track(eventName, eventProperties);
    }
}

async function getDefaultLanguageName(): Promise<string> {
    const languageRepository = Shopware.Service('repositoryFactory').create('language');
    const defaultLanguage = await languageRepository.get(Shopware.Context.api.systemLanguageId!);

    return defaultLanguage!.name;
}

function isEventOfType<N extends EventTypes>(
    eventType: N,
    telemetryEvent: TelemetryEvent<EventTypes>,
): telemetryEvent is TelemetryEvent<N> {
    return telemetryEvent.eventType === eventType;
}
