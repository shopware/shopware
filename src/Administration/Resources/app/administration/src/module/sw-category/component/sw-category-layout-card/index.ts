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
    name: 'sw-category-layout-card',
    load: () => import('./src/sw-category-layout-card'),
    public: {
        props: [
            'category',
            'cmsPage',
            'isLoading',
            'pageTypes',
            'headline',
        ],
        blocks: [
            'sw_category_layout_card',
            'sw_category_detail_layout_preview',
            'sw_category_detail_layout_modal',
            'sw_category_detail_layout_desc',
            'sw_category_detail_layout_desc_info',
            'sw_category_detail_layout_desc_info_headline',
            'sw_category_detail_layout_desc_info_subheadline',
            'sw_category_detail_layout_desc_actions',
            'sw_category_detail_layout_desc_actions_layout',
            'sw_category_detail_layout_desc_actions_designer',
            'sw_category_detail_layout_desc_actions_remove',
        ],
        computed: [
            'pageTypeTitle',
            'pageName',
        ],
        methods: [
            'onLayoutSelect',
            'onLayoutReset',
            'openInPagebuilder',
            'openLayoutModal',
            'closeLayoutModal',
            'resetSlotConfig',
        ],
    },
});
