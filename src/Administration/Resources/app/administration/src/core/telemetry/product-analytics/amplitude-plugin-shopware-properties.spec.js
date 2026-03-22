import { amplitudePluginShopwareProperties } from './amplitude-plugin-shopware-properties';

describe('src/core/telemetry/product-analytics/amplitude-plugin-shopware-properties', () => {
    beforeEach(() => {
        jest.clearAllMocks();
        Shopware.Store.get('context').app.config.version = '6.7.0.0';
        Shopware.Store.get('context').app.config.shopId = 'shop-id';
        Shopware.Store.get('context').app.config.appUrl = 'https://shop.example';
        Shopware.Context.app.systemCurrencyISOCode = 'EUR';
        Shopware.Context.api.systemLanguageId = 'language-id';
        Shopware.Application.view = {
            router: {
                currentRoute: {
                    value: {
                        name: 'sw.dashboard.index',
                        path: '/sw/dashboard/index',
                        fullPath: '/sw/dashboard/index?foo=bar',
                    },
                },
            },
        };

        Object.defineProperty(window.screen, 'width', {
            configurable: true,
            value: 1280,
        });
        Object.defineProperty(window.screen, 'height', {
            configurable: true,
            value: 720,
        });
        Object.defineProperty(window.screen, 'orientation', {
            configurable: true,
            value: {
                type: 'landscape-primary',
            },
        });
    });

    it('adds the default shopware properties plugin', async () => {
        const plugin = amplitudePluginShopwareProperties('English');
        const event = await plugin.execute({
            event_properties: {
                existing: 'value',
            },
        });

        expect(plugin.name).toBe('DefaultShopwareProperties');
        expect(event.event_properties).toEqual(
            expect.objectContaining({
                existing: 'value',
                sw_version: '6.7.0.0',
                sw_shop_id: 'shop-id',
                sw_app_url: 'https://shop.example',
                sw_browser_url: window.location.origin,
                sw_user_agent: window.navigator.userAgent,
                sw_default_language: 'English',
                sw_default_currency: 'EUR',
                sw_screen_width: 1280,
                sw_screen_height: 720,
                sw_screen_orientation: 'landscape',
                sw_page_name: 'sw.dashboard.index',
                sw_page_path: '/sw/dashboard/index',
                sw_page_full_path: '/sw/dashboard/index?foo=bar',
            }),
        );
    });
});
