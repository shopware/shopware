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
    name: 'sw-settings-search-searchable-content',
    load: () => import('./src/sw-settings-search-searchable-content'),
    public: {
        props: [
            'searchConfigId',
            'productSearchConfigs',
        ],
        emits: [
            'edit-change',
        ],
        blocks: [
            'sw_settings_search_searchable_content',
            'sw_settings_search_searchable_content_title',
            'sw_settings_search_searchable_actions',
            'sw_settings_search_searchable_add_item',
            'sw_settings_search_searchable_reset_default',
            'sw_settings_search_searchable_show_example',
            'sw_settings_search_searchable_show_example_link_element',
            'sw_settings_search_searchable_show_example_link',
            'sw_settings_search_searchable_show_example_modal',
            'sw_settings_search_searchable_content_tabs',
            'sw_settings_search_searchable_content_mt_tabs',
            'sw_settings_search_searchable_content_mt_content',
            'sw_settings_search_searchable_content_mt_general_tab_item',
            'sw_settings_search_searchable_content_mt_customfields_tab_item',
            'sw_settings_search_searchable_content_general_tab_title',
            'sw_settings_search_searchable_content_general_tab_item',
        ],
        computed: [
            'productSearchFieldRepository',
            'productSearchFieldCriteria',
            'isListEmpty',
            'getProductSearchFieldColumns',
            'searchableContentTabs',
            'storefrontEsEnable',
        ],
        methods: [
            'onShowExampleModal',
            'onCloseExampleModal',
            'onAddNewConfig',
            'createNewConfigItem',
            'getConfigFieldDefault',
            'onResetToDefault',
            'onChangeTab',
            'loadData',
            'getProductSearchFieldsList',
            'saveConfig',
            'deleteConfig',
        ],
    },
});
