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
    name: 'sw-settings-country-currency-dependent-modal',
    load: () => import('./src/sw-settings-country-currency-dependent-modal'),
    public: {
        props: [
            'currencyDependsValue',
            'countryId',
            'userConfig',
            'userConfigValues',
            'menuOptions',
            'taxFreeType',
            'isLoading',
        ],
        emits: [
            'modal-close',
            'modal-save',
            'base-item-change',
        ],
        blocks: [
            'sw_settings_country_currency_dependent_modal',
            'sw_settings_country_currency_dependent_modal_content',
            'sw_settings_country_currency_dependent_modal_content_hamburger_menu',
            'sw_settings_country_currency_dependent_modal_content_currency_name',
            'sw_settings_country_currency_dependent_grid_column_value',
            'sw_settings_country_currency_dependent_grid_column_is_base_currency',
            'sw_settings_country_currency_dependent_column_actions',
            'sw_settings_country_currency_dependent_grid_column_action_delete',
            'sw_settings_country_currency_dependent_modal_footer',
            'sw_settings_country_currency_dependent_modal_close',
        ],
        computed: [
            'currentUserId',
            'currencyTaxFreeDependentRepository',
            'radioButtonName',
            'countryCurrencyColumns',
        ],
        methods: [
            'closeModal',
            'saveModal',
            'changeCurrencyDependentRow',
            'addCurrencyDependentRow',
            'removeCurrencyDependentRow',
            'updateCheckBoxHamburgerMenu',
            'onChangeBaseCurrency',
            'calculateInheritedPrice',
            'reCalculatorInherited',
            'getPriceByCurrency',
            'createUserConfigValue',
            'createNewUserConfig',
            'updateExistedValue',
            'getCurrencyNameById',
            'getCurrencyById',
        ],
    },
});
