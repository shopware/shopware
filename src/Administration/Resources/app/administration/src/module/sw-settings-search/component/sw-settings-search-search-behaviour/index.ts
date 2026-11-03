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
    name: 'sw-settings-search-search-behaviour',
    load: () => import('./src/sw-settings-search-search-behaviour'),
    public: {
        props: [
            'searchBehaviourConfigs',
            'isLoading',
        ],
        blocks: [
            'sw_settings_search_search_behaviour',
            'sw_settings_search_search_behaviour_title',
            'sw_settings_search_search_behaviour_condition',
            'sw_settings_search_search_behaviour_search_term_length',
        ],
        computed: [
            'conditionsOptions',
        ],
    },
});
