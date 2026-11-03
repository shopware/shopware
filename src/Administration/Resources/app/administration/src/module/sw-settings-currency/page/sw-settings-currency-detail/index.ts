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
    name: 'sw-settings-currency-detail',
    load: () => import('./src/sw-settings-currency-detail'),
    public: {
        props: [
            'currencyId',
        ],
        blocks: [
            'sw_settings_currency_detail',
            'sw_settings_currency_detail_header',
            'sw_settings_currency_detail_actions',
            'sw_settings_currency_detail_actions_abort',
            'sw_settings_currency_detail_actions_save',
            'sw_settings_currency_detail_language_switch',
            'sw_settings_currency_detail_content',
            'sw_settings_currency_detail_content_language_info',
            'sw_settings_currency_detail_content_card',
            'sw_settings_currency_detail_content_field_name',
            'sw_settings_currency_detail_content_field_iso_code',
            'sw_settings_currency_detail_content_field_short_name',
            'sw_settings_currency_detail_content_field_symbol',
            'sw_settings_currency_detail_content_field_factor',
            'sw_settings_currency_detail_content_field_tax_free_from',
            'sw_settings_currency_detail_content_card_price_rounding',
            'sw_settings_currency_detail_content_card_country_price_rounding',
            'sw_settings_currency_detail_content_card_country_price_rounding_toolbar',
            'sw_settings_currency_detail_content_card_country_price_rounding_toolbar_filter',
            'sw_settings_currency_detail_content_card_country_price_rounding_toolbar_button',
            'sw_settings_currency_detail_content_card_country_price_rounding_listing',
            'sw_settings_currency_detail_content_card_country_price_rounding_listing_country_column',
            'sw_settings_currency_detail_content_card_country_price_rounding_listing_item_interval_column',
            'sw_settings_currency_detail_content_card_country_price_rounding_listing_total_interval_column',
            'sw_settings_currency_detail_content_card_country_price_rounding_listing_item_net_column',
            'sw_settings_currency_detail_content_card_country_price_rounding_listing_total_net_column',
            'sw_settings_currency_detail_content_card_country_price_rounding_listing_more_actions',
            'sw_settings_currency_detail_content_card_country_price_rounding_empty',
            'sw_settings_currency_detail_content_card_country_price_rounding_modal',
            'sw_settings_currency_detail_custom_field_sets',
        ],
        computed: [
            'identifier',
            'currencyRepository',
            'currencyCountryRoundingRepository',
            'tooltipSave',
            'tooltipCancel',
            'currencyCountryColumns',
            'currencyCountryRoundingCriteria',
            'emptyStateText',
            'showCustomFields',
        ],
        methods: [
            'createdComponent',
            'loadEntityData',
            'loadCurrencyCountryRoundings',
            'loadCustomFieldSets',
            'saveFinish',
            'onSave',
            'onCancel',
            'invalidateCurrencyCaches',
            'abortOnLanguageChange',
            'saveOnLanguageChange',
            'onChangeLanguage',
            'onChangeCountrySearch',
            'onAddCountry',
            'onCancelEditCountry',
            'onClickEdit',
            'onSaveCurrencyCountry',
        ],
    },
});
