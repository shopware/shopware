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
    name: 'sw-category-detail-cms',
    load: () => import('./src/sw-category-detail-cms'),
    public: {
        props: [
            'isLoading',
        ],
        blocks: [
            'sw_category_detail_cms',
            'sw_category_detail_cms_layout',
            'sw_category_detail_cms_form',
        ],
        computed: [
            'category',
            'cmsPage',
        ],
    },
});
