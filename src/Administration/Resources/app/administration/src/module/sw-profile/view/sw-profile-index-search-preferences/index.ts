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
    name: 'sw-profile-index-search-preferences',
    load: () => import('./src/sw-profile-index-search-preferences'),
    public: {
        blocks: [
            'sw_profile_index_search_preferences',
            'sw_profile_index_search_preferences_search_behavior',
            'sw_profile_index_search_preferences_searchable_elements',
            'sw_profile_index_search_preferences_searchable_elements_content',
            'sw_profile_index_search_preferences_searchable_elements_header',
            'sw_profile_index_search_preferences_searchable_elements_button_select',
            'sw_profile_index_search_preferences_searchable_elements_button_deselect',
            'sw_profile_index_search_preferences_searchable_elements_button_reset',
            'sw_profile_index_search_preferences_searchable_elements_body',
        ],
        computed: [
            'minSearchTermLength',
            'searchPreferences',
            'userSearchPreferences',
            'defaultSearchPreferences',
            'adminEsEnable',
        ],
        methods: [
            'createdComponent',
            'beforeDestroyComponent',
            'getMinSearchTermLength',
            'getDataSource',
            'addEventListeners',
            'removeEventListeners',
            'updateDataSource',
            'getModuleTitle',
            'onChangeSearchPreference',
            'onSelect',
            'onReset',
            'resetSearchPreference',
        ],
    },
});
