import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@framework
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-settings-currency-country-modal',
    load: () => import('./src/sw-settings-currency-country-modal'),
    public: {
        props: [
            'currencyCountryRounding',
        ],
        emits: [
            'edit-cancel',
            'save',
        ],
        slots: [
            'result-label-property',
        ],
        blocks: [
            'sw_settings_currency_country_modal',
            'sw_settings_currency_country_modal_country_select',
            'sw_settings_currency_country_modal_price_rounding',
            'sw_settings_currency_country_modal_footer',
            'sw_settings_currency_country_modal_footer_cancel',
            'sw_settings_currency_country_modal_footer_save',
        ],
        computed: [
            'countryCriteria',
            'countryRepository',
            'assignedCountriesCriteria',
        ],
        methods: [
            'createdComponent',
            'onCancel',
            'onSave',
            'shouldDisableCountry',
        ],
    },
});
