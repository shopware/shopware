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
    name: 'sw-settings-search-excluded-search-terms',
    load: () => import('./src/sw-settings-search-excluded-search-terms'),
    public: {
        props: [
            'searchConfigs',
            'isExcludedTermsLoading',
        ],
        emits: [
            'edit-change',
            'data-load',
        ],
        blocks: [
            'sw_settings_search_excluded_search_terms',
            'sw_settings_search_excluded_search_terms_empty_state',
            'sw_settings_search_excluded_search_terms_empty_state_image',
            'sw_settings_search_excluded_search_terms_empty_state_action',
            'sw_settings_search_excluded_search_terms_grid',
            'sw_settings_search_excluded_search_terms_header_bar',
            'sw_settings_search_excluded_search_terms_filter',
            'sw_settings_search_excluded_search_terms_actions',
            'sw_settings_search_excluded_search_terms_add_button',
            'sw_settings_search_excluded_search_terms_reset_button',
            'sw_settings_search_excluded_search_terms_grid_list',
            'sw_search_excluded_terms_column_actions',
            'sw_search_excluded_terms_list_pagination',
            'sw_search_excliuded_terms_no_results',
        ],
        computed: [
            'searchRepository',
            'getSearchableGeneralColumns',
            'assetFilter',
        ],
        methods: [
            'createdComponent',
            'resetData',
            'addExcludedSearchTerms',
            'onInsertTerm',
            'renderComponent',
            'filterItems',
            'sliceItems',
            'onPagePagination',
            'onDeleteExcludedTerm',
            'onSearchTermChange',
            'selectionChanged',
            'onSaveEdit',
            'getOriginItem',
            'onCancelEdit',
            'onBulkDeleteExcludedTerm',
            'saveConfig',
            'onResetExcludedSearchTermDefault',
        ],
    },
});
