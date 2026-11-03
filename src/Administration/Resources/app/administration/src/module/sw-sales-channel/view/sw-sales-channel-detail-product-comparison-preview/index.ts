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
    name: 'sw-sales-channel-detail-product-comparison-preview',
    load: () => import('./src/sw-sales-channel-detail-product-comparison-preview'),
    public: {
        props: [
            'content',
            'errors',
        ],
        emits: [
            'close',
        ],
        blocks: [
            'sw_sales_channel_detail_product_comparison_preview',
            'sw_sales_channel_detail_product_comparison_preview_modal',
            'sw_sales_channel_detail_product_comparison_preview_modal_errors',
            'sw_sales_channel_detail_product_comparison_preview_modal_content',
        ],
        computed: [
            'editorConfig',
            'displayErrors',
        ],
        methods: [
            'onModalClose',
            'navigateToLine',
        ],
    },
});
