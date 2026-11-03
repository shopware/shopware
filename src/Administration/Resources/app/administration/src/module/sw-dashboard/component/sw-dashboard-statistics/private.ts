import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package after-sales
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-dashboard-statistics',
    load: () => import('./src/sw-dashboard-statistics'),
    public: {
        computed: [
            'rangesValueMap',
            'availableRanges',
            'chartOptionsOrderCount',
            'chartOptionsOrderSum',
            'orderRepository',
            'orderCountSeries',
            'orderCountToday',
            'orderSumMonthSeries',
            'orderSumSeries',
            'orderSumToday',
            'hasOrderToday',
            'hasOrderInMonth',
            'today',
            'todayBucketCount',
            'todayBucketSum',
            'systemCurrencyISOCode',
            'isSessionLoaded',
            'currencyFilter',
            'dateFilter',
        ],
        methods: [
            'calculateTodayBucket',
            'initializeOrderData',
            'getHistoryOrderData',
            'fetchHistoryOrderDataCount',
            'fetchHistoryOrderDataSum',
            'fetchHistory',
            'fetchTodayData',
            'formatDateToISO',
            'formatChartHeadlineDate',
            'orderGridColumns',
            'getVariantFromOrderState',
            'parseDate',
            'onOrdersRangeUpdate',
            'onTurnoverRangeUpdate',
            'getCardSubtitle',
            'getDateAgo',
        ],
    },
});
