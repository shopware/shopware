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
    name: 'sw-settings-search-searchable-content-customfields',
    load: () => import('./src/sw-settings-search-searchable-content-customfields'),
    public: {
        props: [
            'isEmpty',
            'columns',
            'repository',
            'searchConfigs',
            'isLoading',
        ],
        emits: [
            'config-add',
            'data-load',
            'config-save',
            'config-delete',
        ],
        blocks: [
            'sw_settings_search_searchable_content_customfields',
            'sw_settings_search_searchable_content_customfields_empty_state',
            'sw_settings_search_searchable_content_customfields_state_image',
            'sw_settings_search_searchable_content_customfields_empty_state_action',
            'sw_settings_search_searchable_content_customfields_grid',
            'sw_settings_search_searchable_content_customfields_field',
            'sw_settings_search_searchable_content_customfields_field_editor',
            'sw_settings_search_searchable_content_customfields_field_label',
            'sw_settings_search_searchable_content_customfields_ranking',
            'sw_settings_search_searchable_content_customfields_ranking_editor',
            'sw_settings_search_searchable_content_customfields_searchable',
            'sw_settings_search_searchable_content_customfields_searchable_editor',
            'sw_settings_search_searchable_content_customfields_searchable_label',
            'sw_settings_search_searchable_content_customfields_tokenize',
            'sw_settings_search_searchable_content_customfields_tokenize_editor',
            'sw_settings_search_searchable_content_customfields_tokenize_label',
            'sw_settings_search_searchable_content_customfields_columns_actions',
            'sw_settings_search_searchable_content_customfields_columns_actions_edit',
            'sw_settings_search_searchable_content_customfields_columns_actions_delete',
        ],
        computed: [
            'customFieldRepository',
            'customFieldFilteredCriteria',
            'customFieldCriteria',
            'assetFilter',
        ],
        methods: [
            'createdComponent',
            'showCustomFieldWithSet',
            'getMatchingCustomFields',
            'onSelectCustomField',
            'onAddField',
            'onInlineEditSave',
            'onInlineEditCancel',
            'onResetRanking',
            'onRemove',
        ],
    },
});
