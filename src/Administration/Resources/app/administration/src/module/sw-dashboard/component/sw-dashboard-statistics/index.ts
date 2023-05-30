import type EntityCollection from '@shopware-ag/admin-extension-sdk/es/data/_internals/EntityCollection';
import template from './sw-dashboard-statistics.html.twig';
import './sw-dashboard-statistics.scss';

const { Criteria } = Shopware.Data;

type OrderEntity = EntitySchema.order;

type HistoryDateRange = {
    label: string,
    range: number,
    interval: 'hour' | 'day',
    aggregate: 'hour' | 'day',
}

type BucketData = {
    key: string,
    count: number,
    totalAmount: {
        sum: number,
    },
}

type HistoryOrderDataCount = {
    apiAlias: 'order_count_bucket_aggregation',
    buckets: Array<BucketData>,
    name: 'order_count_bucket',
}

type HistoryOrderDataSum = {
    apiAlias: 'order_sum_bucket_aggregation',
    buckets: Array<BucketData>,
    name: 'order_sum_bucket',
}

type HistoryOrderData = HistoryOrderDataCount | HistoryOrderDataSum | null;

interface ComponentData {
    historyOrderDataCount: HistoryOrderDataCount | null,
    historyOrderDataSum: HistoryOrderDataSum | null,
    todayOrderData: EntityCollection<'order'> | null,
    todayOrderDataLoaded: boolean
    todayOrderDataSortBy: 'orderDateTime',
    todayOrderDataSortDirection: 'DESC' | 'ASC',
    ordersDateRange: HistoryDateRange,
    turnoverDateRange: HistoryDateRange,
    isLoading: boolean,
}

/**
 * @package merchant-services
 *
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: [
        'repositoryFactory',
        'stateStyleDataProviderService',
        'acl',
    ],

    data(): ComponentData {
        return {
            historyOrderDataCount: null,
            historyOrderDataSum: null,
            todayOrderData: null,
            todayOrderDataLoaded: false,
            todayOrderDataSortBy: 'orderDateTime',
            todayOrderDataSortDirection: 'DESC',
            ordersDateRange: {
                label: '30Days',
                range: 30,
                interval: 'day',
                aggregate: 'day',
            },
            turnoverDateRange: {
                label: '30Days',
                range: 30,
                interval: 'day',
                aggregate: 'day',
            },
            isLoading: true,
        };
    },

    computed: {
        rangesValueMap(): Array<HistoryDateRange> {
            return [{
                label: '30Days',
                range: 30,
                interval: 'day',
                aggregate: 'day',
            }, {
                label: '14Days',
                range: 14,
                interval: 'day',
                aggregate: 'day',
            }, {
                label: '7Days',
                range: 7,
                interval: 'day',
                aggregate: 'day',
            }, {
                label: '24Hours',
                range: 24,
                interval: 'hour',
                aggregate: 'hour',
            }, {
                label: 'yesterday',
                range: 1,
                interval: 'day',
                aggregate: 'hour',
            }];
        },

        availableRanges(): string[] {
            return this.rangesValueMap.map((range) => range.label);
        },

        chartOptionsOrderCount() {
            return {
                xaxis: {
                    type: 'datetime',
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-call
                    min: this.getDateAgo(this.ordersDateRange).getTime(),
                    labels: {
                        datetimeUTC: false,
                    },
                },
                yaxis: {
                    min: 0,
                    tickAmount: 3,
                    labels: {
                        formatter: (value: string) => { return parseInt(value, 10); },
                    },
                },
            };
        },

        chartOptionsOrderSum() {
            return {
                xaxis: {
                    type: 'datetime',
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-call
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
                        formatter: (value: string) => Shopware.Utils.format.currency(
                            Number.parseFloat(value),
                            Shopware.Context.app.systemCurrencyISOCode as string,
                            2,
                        ),
                    },
                },
            };
        },

        orderRepository() {
            return this.repositoryFactory.create('order');
        },

        orderCountSeries() {
            if (!this.historyOrderDataCount) {
                return [];
            }

            // format data for chart
            const seriesData = this.historyOrderDataCount.buckets.map((data: BucketData) => {
                return { x: this.parseDate(data.key), y: data.count };
            });

            // add empty value for today if there isn't any order, otherwise today would be missing
            if (!this.todayBucketCount) {
                seriesData.push({ x: this.today.getTime(), y: 0 });
            }

            return [{ name: this.$tc('sw-dashboard.monthStats.numberOfOrders'), data: seriesData }];
        },

        orderCountToday() {
            if (this.todayBucketCount) {
                return this.todayBucketCount.count;
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
            const seriesData = this.historyOrderDataSum.buckets.map((data: BucketData) => {
                return { x: this.parseDate(data.key), y: data.totalAmount.sum };
            });

            // add empty value for today if there isn't any order, otherwise today would be missing
            if (!this.todayBucketSum) {
                seriesData.push({ x: this.today.getTime(), y: 0 });
            }

            return [{ name: this.$tc('sw-dashboard.monthStats.totalTurnover'), data: seriesData }];
        },

        orderSumToday() {
            if (this.todayBucketCount) {
                return this.todayBucketCount.totalAmount.sum;
            }
            return 0;
        },

        hasOrderToday() {
            return this.todayOrderData && this.todayOrderData.length > 0;
        },

        hasOrderInMonth() {
            return !!this.historyOrderDataCount && !!this.historyOrderDataSum;
        },

        today() {
            const today = Shopware.Utils.format.dateWithUserTimezone();
            today.setHours(0, 0, 0, 0);
            return today;
        },

        todayBucketCount(): BucketData | null {
            return this.calculateTodayBucket(this.historyOrderDataCount);
        },

        todayBucketSum(): BucketData | null {
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
            async handler() {
                if (this.isSessionLoaded) {
                    await this.initializeOrderData();
                }
            },
        },
    },

    methods: {
        calculateTodayBucket(aggregation: HistoryOrderData): BucketData | null {
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
                        // @ts-expect-error
                        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                        this.historyOrderDataCount = response.aggregations.order_count_bucket;
                    }
                }),
                this.fetchHistoryOrderDataSum().then((response) => {
                    if (response.aggregations) {
                        // @ts-expect-error
                        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
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
                    this.ordersDateRange.aggregate,
                    null,
                    Criteria.sum('totalAmount', 'amountTotal'),
                    // eslint-disable-next-line max-len
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-argument,@typescript-eslint/no-unsafe-member-access
                    Shopware.State.get('session').currentUser?.timeZone ?? 'UTC',
                ),
            );

            criteria.addFilter(Criteria.range('orderDate', {
                gte: this.formatDateToISO(this.getDateAgo(this.ordersDateRange)),
            }));

            return this.orderRepository.search(criteria);
        },

        fetchHistoryOrderDataSum() {
            const criteria = new Criteria(1, 1);

            criteria.addAggregation(
                Criteria.histogram(
                    'order_sum_bucket',
                    'orderDateTime',
                    this.turnoverDateRange.aggregate,
                    null,
                    Criteria.sum('totalAmount', 'amountTotal'),
                    // eslint-disable-next-line max-len
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-argument,@typescript-eslint/no-unsafe-member-access
                    Shopware.State.get('session').currentUser?.timeZone ?? 'UTC',
                ),
            );

            criteria.addAssociation('stateMachineState');

            criteria.addFilter(Criteria.equals('transactions.stateMachineState.technicalName', 'paid'));
            criteria.addFilter(Criteria.range('orderDate', {
                gte: this.formatDateToISO(this.getDateAgo(this.turnoverDateRange)),
            }));

            return this.orderRepository.search(criteria);
        },

        fetchTodayData() {
            const criteria = new Criteria(1, 10);

            criteria.addAssociation('currency');

            criteria.addFilter(Criteria.equals('orderDate', this.formatDateToISO(new Date())));
            criteria.addSorting(Criteria.sort(this.todayOrderDataSortBy, this.todayOrderDataSortDirection));

            return this.orderRepository.search(criteria);
        },

        formatDateToISO(date: Date) {
            return Shopware.Utils.format.toISODate(date, false);
        },

        formatChartHeadlineDate(date: Date) {
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

        getVariantFromOrderState(order: OrderEntity): string {
            const state = order.stateMachineState?.technicalName;
            if (!state) {
                return '';
            }

            return this.stateStyleDataProviderService.getStyle(
                'order.state',
                state,
            ).variant;
        },

        parseDate(date: string): number {
            const parsedDate = new Date(date.replace(/-/g, '/').replace('T', ' ').replace(/\..*|\+.*/, ''));
            return parsedDate.valueOf();
        },

        async onOrdersRangeUpdate(range: string): Promise<void> {
            const ordersDateRange = this.rangesValueMap.find((item: HistoryDateRange) => item.label === range);

            if (!ordersDateRange) {
                throw Error('Range not found');
            }

            this.ordersDateRange = ordersDateRange;

            const response = await this.fetchHistoryOrderDataCount();

            if (response.aggregations) {
                // @ts-expect-error
                // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                this.historyOrderDataCount = response.aggregations.order_count_bucket;
            }
        },

        async onTurnoverRangeUpdate(range: string): Promise<void> {
            const turnoverDateRange = this.rangesValueMap.find((item: HistoryDateRange) => item.label === range);

            if (!turnoverDateRange) {
                throw Error('Range not found');
            }

            this.turnoverDateRange = turnoverDateRange;

            const response = await this.fetchHistoryOrderDataSum();

            if (response.aggregations) {
                // @ts-expect-error
                // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                this.historyOrderDataSum = response.aggregations.order_sum_bucket;
            }
        },

        getCardSubtitle(range: HistoryDateRange): string {
            return `${this.formatChartHeadlineDate(this.getDateAgo(range))} - ${this.formatChartHeadlineDate(this.today)}`;
        },

        getDateAgo(range: HistoryDateRange): Date {
            const date = Shopware.Utils.format.dateWithUserTimezone();

            if (range.interval === 'hour') {
                date.setHours(date.getHours() - range.range);

                return date;
            }

            date.setDate(date.getDate() - range.range);
            date.setHours(0, 0, 0, 0);

            return date;
        },
    },
});

/**
 * @private
 */
export type { HistoryDateRange };
