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
    name: 'sw-seo-main-category',
    load: () => import('./src/sw-seo-main-category'),
    public: {
        props: [
            'currentSalesChannelId',
            'categories',
            'mainCategories',
            'isLoading',
            'allowEdit',
            'overwriteLabel',
        ],
        emits: [
            'main-category-add',
            'main-category-remove',
        ],
        blocks: [
            'sw_seo_main_category',
            'sw_seo_main_category_select',
        ],
        computed: [
            'mainCategoryRepository',
            'isHeadlessSalesChannel',
            'selectedCategory',
        ],
        methods: [
            'createdComponent',
            'onMainCategorySelected',
            'refreshMainCategoryForSalesChannel',
        ],
    },
});
