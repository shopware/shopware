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
    name: 'sw-settings-search-live-search-keyword',
    load: () => import('./src/sw-settings-search-live-search-keyword'),
    public: {
        props: [
            'text',
            'searchTerm',
            'highlightClass',
        ],
        blocks: [
            'sw_settings_search_view_live_search_keyword',
            'sw_settings_search_view_live_search_keyword_highlight',
        ],
        computed: [
            'textIsHighlighted',
            'parsedSearch',
            'parsedMsg',
        ],
        methods: [
            'getClass',
        ],
    },
});
