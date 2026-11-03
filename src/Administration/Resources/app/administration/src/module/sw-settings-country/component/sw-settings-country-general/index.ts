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
    name: 'sw-settings-country-general',
    load: () => import('./src/sw-settings-country-general'),
    public: {
        props: [
            'country',
            'isLoading',
            'userConfig',
            'userConfigValues',
        ],
        emits: [
            'modal-save',
        ],
        blocks: [
            'sw_settings_country_general',
            'sw_settings_country_general_card',
            'sw_settings_country_general_content_field_name',
            'sw_settings_country_general_content_field_position',
            'sw_settings_country_general_content_field_iso',
            'sw_settings_country_general_content_field_iso3',
            'sw_settings_country_general_options_card',
            'sw_settings_country_general_content_field_active',
            'sw_settings_country_general_content_field_shipping_available',
            'sw_settings_country_general_content_field_tax_free',
            'sw_settings_country_general_content_field_tax_free_from_customer',
            'sw_settings_country_general_content_field_tax_free_from_customer_value',
            'sw_settings_country_general_content_show_tax_free_currency_dependent_values',
            'sw_settings_country_general_content_field_tax_free_companies',
            'sw_settings_country_general_content_field_tax_free_from_companies',
            'sw_settings_country_general_content_field_tax_free_from_companies_value',
            'sw_settings_country_general_content_show_company_tax_free_currency_dependent_values',
            'sw_settings_country_general_content_show_currency_dependent_modal',
            'sw_settings_country_general_content_field_check_vat_id_format',
            'sw_settings_country_general_content_field_vat_id_required',
            'sw_settings_country_general_content_field_is_eu',
        ],
        computed: [
            'countryRepository',
            'currencyRepository',
            'isVatIdFormatCheckWithoutEffect',
        ],
        methods: [
            'createdComponent',
            'loadCurrencies',
            'openCustomerTaxModal',
            'openCompanyTaxModal',
            'onToggleCurrencyModal',
            'changeBaseItem',
            'createDataModal',
            'clearMenuOptions',
            'addCheckedHamburgerMenu',
            'addDisabledBaseCurrencyCheckBox',
            'sortCurrencyCheckBox',
            'pushDataFromUserConfig',
            'calculateInheritedPrice',
            'getPriceByCurrency',
            'getCurrencyById',
            'saveCountryCurrencyDependent',
        ],
    },
});
