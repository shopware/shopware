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
    name: 'sw-sales-channel-modal',
    load: () => import('./src/sw-sales-channel-modal'),
    public: {
        emits: [
            'modal-close',
        ],
        blocks: [
            'sw_sales_channel_modal',
            'sw_sales_channel_modal_grid',
            'sw_sales_channel_modal_detail',
            'sw_sales_channel_modal_footer',
            'sw_sales_channel_modal_footer_detail_actions',
            'sw_sales_channel_modal_footer_back',
            'sw_sales_channel_modal_footer_add_channel',
            'sw_sales_channel_modal_footer_actions',
            'sw_sales_channel_modal_footer_cancel',
        ],
        computed: [
            'modalTitle',
            'productStreamRepository',
            'addChannelAction',
        ],
        methods: [
            'createdComponent',
            'onGridOpenDetails',
            'onCloseModal',
            'onAddChannel',
            'openRoute',
            'isProductComparisonSalesChannelType',
        ],
    },
});
