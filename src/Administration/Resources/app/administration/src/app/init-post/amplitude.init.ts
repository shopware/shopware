/**
 * @sw-package framework
 */
import * as amplitude from '@amplitude/analytics-browser';
import type { BaseEvent, EventOptions } from '@amplitude/analytics-types';
import { string } from 'src/core/service/util.service';
import type { TelemetryEvent, EventTypes, TrackableType } from '../../core/telemetry/types';

/**
 * @private
 */
export default async function (): Promise<void> {
    const loginService = Shopware.Service('loginService');

    loginService.addOnLoginListener(async () => {
        await setUserId();
        track('Login');
    });

    loginService.addOnLogoutListener(() => {
        amplitude.setTransport('beacon');
        track('Logout');

        // we need a timeout if we want to include the click on the logout button
        setTimeout(() => {
            amplitude.flush();
            amplitude.reset();
        }, 0);
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
        fetchRemoteConfig: false,
        // serverUrl: use proxy server url here, e.g. usage-data.shopware.io/product-analytics,
    });

    // eslint-disable-next-line listeners/no-missing-remove-event-listener
    Shopware.Utils.EventBus.on('telemetry', pushTelemetryEventToAmplitude);
}

function pushTelemetryEventToAmplitude(telemetryEvent: TelemetryEvent<EventTypes>) {
    if (isEventOfType('page_change', telemetryEvent)) {
        track('Page Viewed', {
            sw_route_from_name: telemetryEvent.eventData.from.name,
            sw_route_from_href: telemetryEvent.eventData.from.path,
            sw_route_to_name: telemetryEvent.eventData.to.name,
            sw_route_to_href: telemetryEvent.eventData.to.path,
            sw_route_to_query: telemetryEvent.eventData.to.fullPath.split('?')[1],
        });
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

        track(eventName, eventProperties);
    }
}

function track(
    eventInput: string | BaseEvent,
    eventProperties?: Record<string, unknown>,
    eventOptions?: EventOptions,
): void {
    amplitude.track(eventInput, eventProperties, eventOptions);
}

async function getDefaultLanguageName(): Promise<string> {
    const languageRepository = Shopware.Service('repositoryFactory').create('language');
    const defaultLanguage = await languageRepository.get(Shopware.Context.api.systemLanguageId!);

    return defaultLanguage!.name;
}

async function setUserId(): Promise<void> {
    await Shopware.Service('userService').getLoaded();
    await Shopware.Service('configService').getLoaded();
    const shopId = Shopware.Store.get('context').app.config.shopId;
    const currentUser = Shopware.Store.get('session').currentUser;

    if (shopId && currentUser?.id) {
        amplitude.setUserId(`${shopId}:${currentUser.id}`);
    }
}

function isEventOfType<N extends EventTypes>(
    eventType: N,
    telemetryEvent: TelemetryEvent<EventTypes>,
): telemetryEvent is TelemetryEvent<N> {
    return telemetryEvent.eventType === eventType;
}
