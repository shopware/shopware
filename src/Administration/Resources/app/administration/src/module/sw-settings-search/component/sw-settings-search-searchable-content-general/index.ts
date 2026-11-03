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
    name: 'sw-settings-search-searchable-content-general',
    load: () => import('./src/sw-settings-search-searchable-content-general'),
    public: {
        props: [
            'isEmpty',
            'columns',
            'repository',
            'searchConfigs',
            'fieldConfigs',
            'isLoading',
        ],
        emits: [
            'data-load',
            'config-save',
        ],
        blocks: [
            'sw_settings_search_searchable_content_general',
            'sw_settings_search_searchable_content_general_empty_state',
            'sw_settings_search_searchable_content_general_state_image',
            'sw_settings_search_searchable_content_general_grid',
            'sw_settings_search_searchable_content_general_field',
            'sw_settings_search_searchable_content_general_field_editor',
            'sw_settings_search_searchable_content_general_field_label',
            'sw_settings_search_searchable_content_general_ranking',
            'sw_settings_search_searchable_content_general_ranking_editor',
            'sw_settings_search_searchable_content_general_searchable',
            'sw_settings_search_searchable_content_general_searchable_editor',
            'sw_settings_search_searchable_content_general_searchable_label',
            'sw_settings_search_searchable_content_general_tokenize',
            'sw_settings_search_searchable_content_general_tokenize_editor',
            'sw_settings_search_searchable_content_general_tokenize_label',
            'sw_settings_search_searchable_content_general_columns_actions',
            'sw_settings_search_searchable_content_general_columns_actions_edit',
            'sw_settings_search_searchable_content_general_pagination',
        ],
        computed: [
            'assetFilter',
        ],
        methods: [
            'getList',
            'getMatchingFields',
            'onSelectField',
            'onInlineEditSave',
            'onInlineEditCancel',
            'onInlineEditItem',
            'onResetRanking',
            'getConfigRankingDefault',
        ],
    },
});
