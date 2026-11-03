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
    name: 'sw-category-detail-seo',
    load: () => import('./src/sw-category-detail-seo'),
    public: {
        props: [
            'isLoading',
        ],
        blocks: [
            'sw_category_detail_seo',
            'sw_category_detail_seo_config',
            'sw_category_detail_seo_list',
        ],
        computed: [
            'category',
        ],
    },
});
