import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@discovery
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module and what it inherits from sw-settings-country-detail,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-settings-country-create',
    extends: 'sw-settings-country-detail',
    load: () => import('./src/sw-settings-country-create'),
    public: {
        blocks: [
            'sw_settings_country_detail_language_switch',
            'sw_settings_country_detail_content_language_info',
            'sw_settings_country_detail',
            'sw_settings_country_detail_header',
            'sw_settings_country_detail_actions',
            'sw_settings_country_detail_actions_abort',
            'sw_settings_country_detail_actions_save',
            'sw_settings_country_detail_content',
            'sw_settings_country_tabs_header',
            'sw_settings_country_mt_tabs_header',
            'sw_setting_country_tabs_setting',
            'sw_setting_country_tabs_state',
            'sw_setting_country_tabs_address_handling',
            'sw_setting_country_tabs_extension',
            'sw_settings_country_tabs_content',
            'sw_settings_country_detail_custom_field_sets',
        ],
        computed: [
            'countryRepository',
            'identifier',
            'stateColumns',
            'isNewCountry',
            'allowSave',
            'countryTabs',
            'tooltipSave',
            'showCustomFields',
        ],
        methods: [
            'createdComponent',
            'saveFinish',
            'loadEntityData',
            'loadCustomFieldSets',
            'loadUserConfig',
            'onSave',
            'onCancel',
            'abortOnLanguageChange',
            'saveOnLanguageChange',
            'onChangeLanguage',
            'getStateColumns',
            'onSaveModal',
            'onUpdateCountry',
        ],
    },
});
