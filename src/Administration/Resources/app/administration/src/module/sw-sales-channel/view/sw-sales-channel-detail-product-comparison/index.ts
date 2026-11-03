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
    name: 'sw-sales-channel-detail-product-comparison',
    load: () => import('./src/sw-sales-channel-detail-product-comparison'),
    public: {
        props: [
            'salesChannel',
            'productExport',
            'isLoading',
        ],
        blocks: [
            'sw_sales_channel_detail_base',
            'sw_sales_channel_detail_product_comparison',
            'sw_sales_channel_detail_product_comparison_header',
            'sw_sales_channel_detail_product_comparison_input_header',
            'sw_sales_channel_detail_product_comparison_body',
            'sw_sales_channel_detail_product_comparison_input_body',
            'sw_sales_channel_detail_product_comparison_footer',
            'sw_sales_channel_detail_product_comparison_input_footer',
            'sw_sales_channel_detail_product_comparison_actions',
            'sw_sales_channel_detail_product_comparison_actions_test_button',
            'sw_sales_channel_detail_product_comparison_actions_preview_button',
            'sw_sales_channel_detail_product_comparison_actions_reset_button',
            'sw_sales_channel_detail_product_comparison_actions_preview_component',
        ],
        computed: [
            'isAgenticCommerce',
            'editorConfig',
            'productExportRepository',
            'domainRepository',
            'salesChannelRepository',
            'mainNavigationCriteria',
            'outerCompleterFunctionHeader',
            'outerCompleterFunctionBody',
            'outerCompleterFunctionFooter',
        ],
        methods: [
            'validateTemplate',
            'preview',
            'outerCompleterFunction',
            'onPreviewClose',
            'resetToDefault',
            'resetValid',
        ],
    },
});
