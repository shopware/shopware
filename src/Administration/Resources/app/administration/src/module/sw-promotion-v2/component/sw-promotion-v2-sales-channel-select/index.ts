import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-promotion-v2-sales-channel-select',
    load: () => import('./src/sw-promotion-v2-sales-channel-select'),
    public: {
        props: [
            'promotion',
        ],
        blocks: [
            'sw_promotion_v2_sales_channel_selection',
            'sw_promotion_v2_sales_channel_selection_label',
            'sw_promotion_v2_sales_channel_selection_result_label',
        ],
        computed: [
            'salesChannelRepository',
            'promotionSalesChannelRepository',
            'salesChannelIds',
            'salesChannelCriteria',
        ],
        methods: [
            'createdComponent',
            'getChangeset',
            'getAssociationBySalesChannelId',
            'handleLocalMode',
            'handleWithRepository',
        ],
    },
});
