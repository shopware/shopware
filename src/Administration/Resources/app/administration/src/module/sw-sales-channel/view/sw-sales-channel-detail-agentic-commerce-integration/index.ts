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
    name: 'sw-sales-channel-detail-agentic-commerce-integration',
    load: () => import('./src/sw-sales-channel-detail-agentic-commerce-integration'),
    public: {
        props: [
            'salesChannel',
            'productExport',
            'productComparisonAccessUrl',
            'isLoading',
        ],
        blocks: [
            'sw_sales_channel_detail_agentic_commerce_integration',
            'sw_sales_channel_detail_agentic_commerce_integration_card',
            'sw_sales_channel_detail_agentic_commerce_integration_introduction',
            'sw_sales_channel_detail_agentic_commerce_integration_steps',
            'sw_sales_channel_detail_agentic_commerce_integration_feed_url',
        ],
        computed: [
            'providerName',
            'isOpenAi',
            'feedUrl',
            'integrationSnippetPrefix',
        ],
    },
});
