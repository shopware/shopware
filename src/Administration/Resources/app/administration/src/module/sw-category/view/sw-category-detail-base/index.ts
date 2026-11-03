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
    name: 'sw-category-detail-base',
    load: () => import('./src/sw-category-detail-base'),
    public: {
        props: [
            'isLoading',
        ],
        blocks: [
            'sw_category_detail_base',
            'sw_category_detail_information',
            'sw_category_detail_information_name',
            'sw_category_detail_information_active',
            'sw_category_detail_information_tags',
            'sw_category_detail_information_type',
            'sw_category_detail_information_type_select',
            'sw_category_detail_entry_point',
            'sw_category_detail_link',
            'sw_category_detail_menu',
            'sw_category_detail_attribute_sets',
        ],
        computed: [
            'customFieldSetsArray',
            'categoryTypes',
            'typeLinkLabel',
            'categoryTypeHelpText',
            'isSalesChannelEntryPoint',
            'category',
            'isCategoryColumn',
        ],
    },
});
