import template from './sw-dashboard-statistics.html.twig';
import './sw-dashboard-statistics.scss';

const { Criteria } = Shopware.Data;

/**
 * @deprecated tag:v6.5.0 - Will be private
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'stateStyleDataProviderService',
        'acl',
    ],

    data() {
        return {
            historyOrderDataCount: null,
            historyOrderDataSum: null,
            todayOrderData: [],
            todayOrderDataLoaded: false,
            todayOrderDataSortBy: 'orderDateTime',
            todayOrderDataSortDirection: 'DESC',
            /**
             * @deprecated tag:v6.5.0 - Will be removed in v6.5.0.
             * Please use `rangesValueMap` and `ordersDateRange` or `turnoverDateRange` instead.
             */
            statisticDateRanges: {
                value: '30Days',
                options: {
                    '30Days': 30,
                    '14Days': 14,
                    '7Days': 7,
                    '24Hours': 24,
                    yesterday: 1,
                },
            },
            ordersDateRange: '30Days',
            turnoverDateRange: '30Days',
            isLoading: true,
        };
    },

    computed: {
        rangesValueMap() {
            return {
                '30Days': 30,
                '14Days': 14,
                '7Days': 7,
                '24Hours': 24,
                yesterday: 1,
            };
        },
        chartOptionsOrderCount() {
            return {
                xaxis: {
                    type: 'datetime',
                    min: this.getDateAgo(this.ordersDateRange).getTime(),
                    labels: {
                        datetimeUTC: false,
                    },
                },
                yaxis: {
                    min: 0,
                    tickAmount: 3,
                    labels: {
                        formatter: (value) => { return parseInt(value, 10); },
                    },
                },
            };
        },

        chartOptionsOrderSum() {
            return {
                xaxis: {
                    type: 'datetime',
                    min: this.getDateAgo(this.turnoverDateRange).getTime(),
                    labels: {
                        datetimeUTC: false,
                    },
                },
                yaxis: {
                    min: 0,
                    tickAmount: 5,
                    labels: {
                        // price aggregations do not support currencies yet, see NEXT-5069
                        formatter: (value) => this.$options.filters.currency(value, null, 2),
                    },
                },
            };
        },

        orderRepository() {
            return this.repositoryFactory.create('order');
        },

        orderCountMonthSeries() {
            return this.orderCountSeries;
        },

        orderCountSeries() {
            if (!this.historyOrderDataCount) {
                return [];
            }

            // format data for chart
            const seriesData = this.historyOrderDataCount.buckets.map((data) => {
                return { x: this.parseDate(data.key), y: data.count };
            });

            // add empty value for today if there isn't any order, otherwise today would be missing
            if (!this.todayBucket) {
                seriesData.push({ x: this.today.getTime(), y: 0 });
            }

            return [{ name: this.$tc('sw-dashboard.monthStats.numberOfOrders'), data: seriesData }];
        },

        orderCountToday() {
            if (this.todayBucket) {
                return this.todayBucket.count;
            }
            return 0;
        },

        orderSumMonthSeries() {
            return this.orderSumSeries;
        },

        orderSumSeries() {
            if (!this.historyOrderDataSum) {
                return [];
            }

            // format data for chart
            const seriesData = this.historyOrderDataSum.buckets.map((data) => {
                return { x: this.parseDate(data.key), y: data.totalAmount.sum };
            });

            // add empty value for today if there isn't any order, otherwise today would be missing
            if (!this.todayBucketSum) {
                seriesData.push({ x: this.today.getTime(), y: 0 });
            }

            return [{ name: this.$tc('sw-dashboard.monthStats.totalTurnover'), data: seriesData }];
        },

        orderSumToday() {
            if (this.todayBucket) {
                return this.todayBucket.totalAmount.sum;
            }
            return 0;
        },

        hasOrderToday() {
            return this.todayOrderData.length > 0;
        },

        hasOrderInMonth() {
            return !!this.historyOrderDataCount && !!this.historyOrderDataSum;
        },

        today() {
            const today = Shopware.Utils.format.dateWithUserTimezone();
            today.setHours(0, 0, 0, 0);
            return today;
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed. Use todayBucketCount instead.
         */
        todayBucket() {
            return this.todayBucketCount;
        },

        todayBucketCount() {
            return this.calculateTodayBucket(this.historyOrderDataCount);
        },

        todayBucketSum() {
            return this.calculateTodayBucket(this.historyOrderDataSum);
        },

        systemCurrencyISOCode() {
            return Shopware.Context.app.systemCurrencyISOCode;
        },

        isSessionLoaded() {
            return !Shopware.State.get('session')?.userPending;
        },
    },

    watch: {
        isSessionLoaded: {
            immediate: true,
            handler() {
                if (this.isSessionLoaded) {
                    this.initializeOrderData();
                }
            },
        },
    },

    methods: {
        calculateTodayBucket(aggregation) {
            const buckets = aggregation?.buckets;

            if (!buckets) {
                return null;
            }

            const today = this.today;
            // search for stats with same timestamp as today
            const findDateStats = buckets.find((dateCount) => {
                // when date exists
                if (dateCount.key) {
                    // if time is today
                    const date = new Date(dateCount.key);

                    return date.setHours(0, 0, 0, 0) === today.setHours(0, 0, 0, 0);
                }

                return false;
            });

            if (findDateStats) {
                return findDateStats;
            }
            return null;
        },

        async initializeOrderData() {
            if (!this.acl.can('order.viewer')) {
                this.isLoading = false;

                return;
            }

            this.todayOrderDataLoaded = false;

            await this.getHistoryOrderData();
            this.todayOrderData = await this.fetchTodayData();
            this.todayOrderDataLoaded = true;
            this.isLoading = false;
        },

        getHistoryOrderData() {
            return Promise.all([
                this.fetchHistoryOrderDataCount().then((response) => {
                    if (response.aggregations) {
                        this.historyOrderDataCount = response.aggregations.order_count_bucket;
                    }
                }),
                this.fetchHistoryOrderDataSum().then((response) => {
                    if (response.aggregations) {
                        this.historyOrderDataSum = response.aggregations.order_sum_bucket;
                    }
                }),
            ]);
        },

        fetchHistoryOrderDataCount() {
            const criteria = new Criteria(1, 1);

            criteria.addAggregation(
                Criteria.histogram(
                    'order_count_bucket',
                    'orderDateTime',
                    this.getTimeUnitInterval(this.ordersDateRange),
                    null,
                    Criteria.sum('totalAmount', 'amountTotal'),
                    Shopware.State.get('session').currentUser?.timeZone ?? 'UTC',
                ),
            );

            criteria.addFilter(Criteria.range('orderDate', {
                gte: this.formatDate(this.getDateAgo(this.ordersDateRange)),
            }));

            return this.orderRepository.search(criteria);
        },

        fetchHistoryOrderDataSum() {
            const criteria = new Criteria(1, 1);

            criteria.addAggregation(
                Criteria.histogram(
                    'order_sum_bucket',
                    'orderDateTime',
                    this.getTimeUnitInterval(this.turnoverDateRange),
                    null,
                    Criteria.sum('totalAmount', 'amountTotal'),
                    Shopware.State.get('session').currentUser?.timeZone ?? 'UTC',
                ),
            );

            criteria.addAssociation('stateMachineState');

            criteria.addFilter(Criteria.equals('transactions.stateMachineState.technicalName', 'paid'));
            criteria.addFilter(Criteria.range('orderDate', {
                gte: this.formatDate(this.getDateAgo(this.turnoverDateRange)),
            }));

            return this.orderRepository.search(criteria);
        },

        fetchTodayData() {
            const criteria = new Criteria(1, 10);

            criteria.addAssociation('currency');

            criteria.addFilter(Criteria.equals('orderDate', this.formatDate(new Date())));
            criteria.addSorting(Criteria.sort(this.todayOrderDataSortBy, this.todayOrderDataSortDirection));

            return this.orderRepository.search(criteria);
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed. Use formatDateToISO instead.
         */
        formatDate(date) {
            return this.formatDateToISO(date);
        },

        formatDateToISO(date) {
            return Shopware.Utils.format.toISODate(date, false);
        },

        formatChartHeadlineDate(date) {
            const lastKnownLang = Shopware.Application.getContainer('factory').locale.getLastKnownLocale();

            return date.toLocaleDateString(lastKnownLang, {
                day: 'numeric',
                month: 'short',
            });
        },

        orderGridColumns() {
            return [{
                property: 'orderNumber',
                label: 'sw-order.list.columnOrderNumber',
                routerLink: 'sw.order.detail',
                allowResize: true,
                primary: true,
            }, {
                property: 'orderDateTime',
                dataIndex: 'orderDateTime',
                label: 'sw-dashboard.todayStats.orderTime',
                allowResize: true,
                primary: false,
            }, {
                property: 'orderCustomer.firstName',
                dataIndex: 'orderCustomer.firstName,orderCustomer.lastName',
                label: 'sw-order.list.columnCustomerName',
                allowResize: true,
            }, {
                property: 'stateMachineState.name',
                label: 'sw-order.list.columnState',
                allowResize: true,
            }, {
                property: 'amountTotal',
                label: 'sw-order.list.columnAmount',
                align: 'right',
                allowResize: true,
            }];
        },

        getVariantFromOrderState(order) {
            return this.stateStyleDataProviderService.getStyle('order.state', order.stateMachineState.technicalName).variant;
        },

        parseDate(date) {
            const parsedDate = new Date(date.replace(/-/g, '/').replace('T', ' ').replace(/\..*|\+.*/, ''));
            return parsedDate.valueOf();
        },

        async onOrdersRangeUpdate(value) {
            this.ordersDateRange = value;

            const response = await this.fetchHistoryOrderDataCount();

            if (response.aggregations) {
                this.historyOrderDataCount = response.aggregations.order_count_bucket;
            }
        },

        async onTurnoverRangeUpdate(value) {
            this.turnoverDateRange = value;

            const response = await this.fetchHistoryOrderDataSum();

            if (response.aggregations) {
                this.historyOrderDataSum = response.aggregations.order_sum_bucket;
            }
        },

        getTimeUnitInterval(range) {
            if (range === 'yesterday' || range === '24Hours') {
                return 'hour';
            }

            return 'day';
        },

        getCardSubtitle(range) {
            return `${this.formatChartHeadlineDate(this.getDateAgo(range))} - ${this.formatChartHeadlineDate(this.today)}`;
        },

        getDateAgo(range) {
            const date = Shopware.Utils.format.dateWithUserTimezone();
            const dateRange = this.rangesValueMap[range] ?? 0;

            // special case for "24Hours": return directly because we need hours instead of days
            if (range === '24Hours') {
                date.setHours(date.getHours() - dateRange);

                return date;
            }

            date.setDate(date.getDate() - dateRange);
            date.setHours(0, 0, 0, 0);

            return date;
        },
    },
};
