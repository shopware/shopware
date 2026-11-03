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
    name: 'sw-sales-channel-modal-grid',
    load: () => import('./src/sw-sales-channel-modal-grid'),
    public: {
        props: [
            'productStreamsExist',
            'productStreamsLoading',
            'addChannelAction',
        ],
        emits: [
            'grid-channel-add',
            'grid-detail-open',
        ],
        blocks: [
            'sw_sales_channel_modal_grid',
            'sw_sales_channel_grid_columns',
            'sw_sales_channel_grid_columns_icon',
            'sw_sales_channel_grid_columns_content',
            'sw_sales_channel_grid_columns_actions',
            'sw_sales_channel_modal_grid_loader',
        ],
        computed: [
            'salesChannelTypeRepository',
        ],
        methods: [
            'createdComponent',
            'onAddChannel',
            'onOpenDetail',
            'isAgenticCommerceSalesChannelType',
            'isProductComparisonSalesChannelType',
            'getTooltip',
            'isDisabled',
            'showAgenticCommerceType',
        ],
    },
});
