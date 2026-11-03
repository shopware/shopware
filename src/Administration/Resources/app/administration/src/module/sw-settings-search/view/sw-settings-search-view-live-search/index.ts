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
    name: 'sw-settings-search-view-live-search',
    load: () => import('./src/sw-settings-search-view-live-search'),
    public: {
        props: [
            'currentSalesChannelId',
            'searchTerms',
            'searchResults',
            'isLoading',
        ],
        blocks: [
            'sw_settings_search_view_live_search',
            'sw_settings_search_view_live_search_content_card',
        ],
        computed: [
            'storefrontEsEnable',
        ],
    },
});
