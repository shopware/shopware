import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@discovery
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-settings-country-currency-hamburger-menu',
    load: () => import('./src/sw-settings-country-currency-hamburger-menu'),
    public: {
        props: [
            'isLoading',
            'options',
        ],
        emits: [
            'currency-change',
        ],
        blocks: [
            'sw_settings_country_currency_hamburger_menu',
            'sw_settings_country_currency_hamburger_menu_trigger',
            'sw_country_currency_hamburger_menu_icon',
            'sw_country_currency_hamburger_menu_list',
            'sw_country_currency_hamburger_menu_item',
            'sw_country_currency_hamburger_menu_item_visibility_checkbox',
            'sw_settings_country_currency_hamburger_menu_loader',
        ],
        methods: [
            'onCheckCurrency',
        ],
    },
});
