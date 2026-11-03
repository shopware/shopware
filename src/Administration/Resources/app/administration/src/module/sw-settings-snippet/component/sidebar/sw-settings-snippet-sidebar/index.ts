import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-settings-snippet-sidebar',
    load: () => import('./src/sw-settings-snippet-sidebar'),
    public: {
        props: [
            'filterItems',
            'authorFilters',
            'filterSettings',
        ],
        emits: [
            'sw-sidebar-close',
            'sw-sidebar-open',
            'change',
            'sw-sidebar-collaps-refresh-grid',
            'sidebar-reset-all',
        ],
        blocks: [
            'sw_settings_snippet_grid_sidebar',
            'sw_settings_snippet_grid_sidebar_refresh',
            'sw_settings_snippet_grid_sidebar_filter',
            'sw_settings_snippet_grid_sidebar_filter_only_empty',
            'sw_settings_snippet_grid_sidebar_filter_custom',
            'sw_settings_snippet_grid_sidebar_filter_added',
            'sw_settings_snippet_grid_sidebar_filter_author',
            'sw_settings_snippet_grid_sidebar_filter_more',
        ],
        computed: [
            'activeFilterNumber',
            'isExpandedAuthorFilters',
            'isExpandedMoreFilters',
        ],
        methods: [
            'registerFilterSidebarItem',
            'closeContent',
            'openFilterSidebar',
            'onChange',
            'onRefresh',
            'resetAll',
        ],
    },
});
