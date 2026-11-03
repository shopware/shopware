import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-sales-channel-detail-analytics',
    load: () => import('./src/sw-sales-channel-detail-analytics'),
    public: {
        props: [
            'isLoading',
            'salesChannel',
        ],
        blocks: [
            'sw_sales_channel_detail_analytics',
            'sw_sales_channel_detail_analytics_configuration',
            'sw_sales_channel_detail_analytics_headline',
            'sw_sales_channel_detail_analytics_description',
            'sw_sales_channel_detail_analytics_fields_tracking_id',
            'sw_sales_channel_detail_analytics_fields_container',
            'sw_sales_channel_detail_analytics_fields_active',
            'sw_sales_channel_detail_analytics_fields_anonymize_ip',
            'sw_sales_channel_detail_analytics_tracking',
            'sw_sales_channel_detail_analytics_tracking_description',
            'sw_sales_channel_detail_analytics_fields_track_orders',
            'sw_sales_channel_detail_analytics_fields_enhanced_conversions',
            'sw_sales_channel_detail_analytics_fields_track_offcanvas_cart',
        ],
        methods: [
            'createAnalyticsData',
        ],
    },
});
