import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-search-more-results',
    load: () => import('./src/sw-search-more-results'),
    public: {
        props: [
            'entity',
            'term',
        ],
        slots: [
            'content',
        ],
        blocks: [
            'sw_search_more_results',
            'sw_search_more_results_content',
            'sw_search_more_results_slot_content',
        ],
        computed: [
            'moduleFactory',
            'searchTypeRoute',
            'searchTypes',
            'searchContent',
        ],
    },
});
