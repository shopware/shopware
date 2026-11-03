import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package inventory
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-settings-search',
    load: () => import('./src/sw-settings-search'),
    public: {
        blocks: [
            'sw_settings_search_index',
            'sw_settings_search_smart_bar_header',
            'sw_settings_search_smart_bar_header_title_text',
            'sw_settings_search_language_switch',
            'sw_settings_search_smart_bar_actions',
            'sw_settings_search_smart_bar_actions_save',
            'sw_settings_search_content',
            'sw_settings_search_tabs_header',
            'sw_settings_search_mt_tabs_header',
            'sw_setting_search_tabs_general',
            'sw_setting_search_tabs_live_search',
            'sw_setting_search_tabs_after',
            'sw_settings_search_tabs_content',
            'sw_settings_search_discard_model',
        ],
        computed: [
            'productSearchRepository',
            'productSearchFieldRepository',
            'productSearchConfigsCriteria',
            'productDefaultConfigsCriteria',
            'allowSave',
            'settingsSearchTabs',
            'tooltipSave',
        ],
        methods: [
            'createdComponent',
            'getProductSearchConfigs',
            'getDefaultSearchConfig',
            'createDefaultSearchConfig',
            'createConfigFields',
            'onSaveDefaultSearchConfig',
            'onChangeLanguage',
            'onTabChange',
            'onSaveSearchSettings',
            'saveFinish',
            'fetchSalesChannels',
            'unsavedDataLeaveHandler',
            'onSalesChannelChanged',
            'onLiveSearchResultsChanged',
            'onEditChanged',
            'onConfirmLeave',
            'onCloseLeaveModal',
            'onCancelLeaveModal',
        ],
    },
});
