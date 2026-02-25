/**
 * @sw-package framework
 */
import createConsentEventHandler from 'src/core/consent/handlers';
import createTelemetryEventHandler from './amplitude.telemetry-handlers';

/**
 * @private
 */
export default async function (): Promise<void> {
    const analyticsGatewayUrl = Shopware.Store.get('context').app.analyticsGatewayUrl;

    if (!analyticsGatewayUrl) {
        return;
    }

    const amplitude = await import('@amplitude/analytics-browser');
    const anonymousAmplitude = amplitude.createInstance();

    Shopware.Service('loginService').addOnLogoutListener(() => {
        amplitude.setTransport('beacon');
        anonymousAmplitude.setTransport('beacon');
        anonymousAmplitude.flush();
        anonymousAmplitude.reset();
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
    // The real key will be added by the gateway
    amplitude.init('placeholder-apikey', undefined, {
        autocapture: false,
        serverZone: 'EU',
        appVersion: Shopware.Store.get('context').app.config.version as string,
        trackingOptions: {
            ipAddress: false,
            language: false,
            platform: false,
        },
        fetchRemoteConfig: false,
        serverUrl: `${analyticsGatewayUrl}/event`,
    });

    anonymousAmplitude.init('placeholder-apikey', undefined, {
        autocapture: false,
        serverZone: 'EU',
        appVersion: Shopware.Store.get('context').app.config.version as string,
        trackingOptions: {
            ipAddress: false,
            language: false,
            platform: false,
        },
        fetchRemoteConfig: false,
        serverUrl: `${analyticsGatewayUrl}/event/anonymous`,
    });

    const pushTelemetryEventToAmplitude = createTelemetryEventHandler(amplitude);
    const pushConsentEventToAmplitude = createConsentEventHandler(anonymousAmplitude);

    // eslint-disable-next-line listeners/no-missing-remove-event-listener
    Shopware.Utils.EventBus.on('telemetry', pushTelemetryEventToAmplitude);
    // eslint-disable-next-line listeners/no-missing-remove-event-listener
    Shopware.Utils.EventBus.on('consent', pushConsentEventToAmplitude);
}

async function getDefaultLanguageName(): Promise<string> {
    const languageRepository = Shopware.Service('repositoryFactory').create('language');
    const defaultLanguage = await languageRepository.get(Shopware.Context.api.systemLanguageId!);

    return defaultLanguage!.name;
}
