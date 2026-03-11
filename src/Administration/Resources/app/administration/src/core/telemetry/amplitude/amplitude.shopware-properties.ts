/**
 * @sw-package framework
 */
import type * as AmplitudeClient from '@amplitude/analytics-browser';

type AmplitudeModule = typeof AmplitudeClient;

/**
 * @private
 */
export function addDefaultShopwarePropertiesPlugin(amplitude: AmplitudeModule, defaultLanguageName: string): void {
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
}

/**
 * @private
 */
export async function getDefaultLanguageName(): Promise<string> {
    const languageRepository = Shopware.Service('repositoryFactory').create('language');
    const defaultLanguage = await languageRepository.get(Shopware.Context.api.systemLanguageId!);

    return defaultLanguage!.name;
}
