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
    name: 'sw-order-detail-general',
    load: () => import('./src/sw-order-detail-general'),
    public: {
        props: [
            'orderId',
            'isSaveSuccessful',
        ],
        emits: [
            'save-and-recalculate',
            'save-edits',
            'recalculate-and-reload',
            'save-and-reload',
            'reload-entity-data',
            'error',
        ],
        blocks: [
            'sw_order_detail_general',
            'sw_order_detail_general_info_card',
            'sw_order_detail_general_info',
            'sw_order_detail_general_line_items_card',
            'sw_order_detail_general_line_items_card_rows',
            'sw_order_detail_general_line_items_grid',
            'sw_order_detail_general_line_items_summary',
            'sw_order_detail_general_line_items_summary_entries',
            'sw_order_detail_general_line_items_summary_amount',
            'sw_order_detail_general_line_items_summary_shipping_cost',
            'sw_order_detail_general_line_items_summary_amount_without_tax',
            'sw_order_detail_general_line_items_summary_taxes',
            'sw_order_detail_general_line_items_summary_amount_total',
            'sw_order_detail_general_line_items_summary_amount_free_tax',
        ],
        computed: [
            'isLoading',
            'loading',
            'order',
            'versionContext',
            'delivery',
            'deliveryDiscounts',
            'shippingCostsDetail',
            'sortedCalculatedTaxes',
            'taxStatus',
            'displayRounded',
            'orderTotal',
            'currency',
            'currencyFilter',
        ],
        methods: [
            'sortByTaxRate',
            'onShippingChargeEdited',
            'onShippingChargeUpdated',
            'saveAndRecalculate',
            'onSaveEdits',
            'recalculateAndReload',
            'updateLoading',
            'reloadEntityData',
            'discardLineItemEdit',
            'saveAndReload',
            'showError',
        ],
    },
});
