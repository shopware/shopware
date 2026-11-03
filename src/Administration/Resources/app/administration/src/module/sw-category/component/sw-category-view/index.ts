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
    name: 'sw-category-view',
    load: () => import('./src/sw-category-view'),
    public: {
        props: [
            'isLoading',
            'type',
        ],
        blocks: [
            'sw_category_view',
            'sw_category_view_language_info',
            'sw_category_view_column_info',
            'sw_category_view_tabs',
            'sw_category_view_mt_tabs',
            'sw_category_view_tabs_general',
            'sw_category_view_tabs_products',
            'sw_category_view_tabs_cms',
            'sw_category_view_tabs_seo',
            'sw_category_view_content',
        ],
        computed: [
            'category',
            'isCategoryColumn',
            'cmsPage',
            'isPage',
            'isCustomEntity',
            'categoryViewTabs',
        ],
    },
});
