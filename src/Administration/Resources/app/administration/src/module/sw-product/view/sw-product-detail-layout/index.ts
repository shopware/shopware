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
    name: 'sw-product-detail-layout',
    load: () => import('./src/sw-product-detail-layout'),
    public: {
        blocks: [
            'sw_product_detail_layout',
            'sw_product_detail_layout_assignment',
            'sw_product_detail_layout_content',
            'sw_product_detail_layout_modal',
            'sw_product_detail_layout_cms_config',
            'sw_product_detail_layout_cms_config_form',
            'sw_product_detail_layout_cms_content_info',
        ],
        computed: [
            'cmsPageRepository',
            'cmsPageId',
            'showCmsForm',
            'product',
            'isLoading',
            'cmsPageCriteria',
            'languageId',
            'currentPage',
            'cmsPageState',
        ],
        methods: [
            'createdComponent',
            'onOpenLayoutModal',
            'onCloseLayoutModal',
            'onOpenInPageBuilder',
            'onSelectLayout',
            'handleGetCmsPage',
            'updateCmsPageDataMapping',
            'onResetLayout',
            'elementUpdate',
            'resetSlotConfig',
        ],
    },
});
