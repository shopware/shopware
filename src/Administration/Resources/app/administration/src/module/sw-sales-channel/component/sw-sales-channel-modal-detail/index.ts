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
    name: 'sw-sales-channel-modal-detail',
    load: () => import('./src/sw-sales-channel-modal-detail'),
    public: {
        props: [
            'detailType',
        ],
        blocks: [
            'sw_sales_channel_modal_detail',
            'sw_sales_channel_modal_detail_header',
            'sw_sales_channel_modal_detail_header_icon',
            'sw_sales_channel_modal_detail_header_meta',
            'sw_sales_channel_modal_detail_description',
            'sw_sales_channel_modal_detail_screenshots',
        ],
    },
});
