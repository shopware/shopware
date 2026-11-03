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
    name: 'sw-sales-channel-detail-hreflang',
    load: () => import('./src/sw-sales-channel-detail-hreflang'),
    public: {
        props: [
            'salesChannel',
            'disabled',
        ],
        blocks: [
            'sw_sales_channel_detail_hreflang',
            'sw_sales_channel_detail_hreflang_title',
            'sw_sales_channel_detail_hreflang_content',
            'sw_sales_channel_detail_hreflang_content_description',
            'sw_sales_channel_detail_hreflang_content_enable',
            'sw_sales_channel_detail_hreflang_content_domain',
        ],
        computed: [
            'domainCriteria',
        ],
    },
});
