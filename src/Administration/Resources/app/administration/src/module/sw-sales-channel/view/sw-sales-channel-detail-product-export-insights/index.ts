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
    name: 'sw-sales-channel-detail-product-export-insights',
    load: () => import('./src/sw-sales-channel-detail-product-export-insights'),
    public: {
        props: [
            'salesChannel',
        ],
        blocks: [
            'sw_sales_channel_detail_product_export_insights',
        ],
        computed: [
            'orderTrackingRepository',
            'customerTrackingRepository',
            'currencyFilter',
            'availableRanges',
            'defaultRangeIndex',
            'orderCountSummary',
            'customerCountSummary',
            'orderSumSummary',
            'chartOptionsOrderCount',
            'chartOptionsCustomerCount',
            'chartOptionsOrderSum',
            'orderCountCriteria',
            'customerCountCriteria',
            'orderSumCriteria',
            'orderCountSeries',
            'customerCountSeries',
            'orderSumSeries',
            'today',
        ],
        methods: [
            'createdComponent',
            'fetchData',
            'onOrderCountRangeUpdate',
            'onCustomerCountRangeUpdate',
            'onOrderSumRangeUpdate',
            'getHistoryOrderCountData',
            'getHistoryCustomerCountData',
            'getHistoryOrderSumData',
            'extractHistoryOrderData',
            'extractHistoryCustomerData',
            'extractTurnoverData',
            'formatOrderDataArray',
            'formatCustomerDataArray',
            'getTrackingOrderAmountTotal',
            'dateAgoValue',
            'getTimeUnitInterval',
            'getChartRangeSubtitle',
            'formatDate',
            'formatChartHeadlineDate',
            'formatDateStringToAggregationTime',
            'aggregateByDateTime',
            'getChartOptions',
            'tooltipDateFormatOptions',
            'formatTooltipDate',
        ],
    },
});
