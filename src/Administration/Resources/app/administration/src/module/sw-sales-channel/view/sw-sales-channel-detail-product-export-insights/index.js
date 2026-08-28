/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-detail-product-export-insights.html.twig';
import './sw-sales-channel-detail-product-export-insights.scss';

const { Criteria } = Shopware.Data;

const DEFAULT_DATE_RANGE_OPTIONS = {
    '180Days': 180,
    '30Days': 30,
    '14Days': 14,
    '7Days': 7,
    '24Hours': 24,
    yesterday: 1,
};

const DEFAULT_AVAILABLE_RANGES = Object.keys(DEFAULT_DATE_RANGE_OPTIONS);

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
    ],

    props: {
        salesChannel: {
            required: true,
        },
    },

    data() {
        return {
            historyOrderDataCount: [],
            historyOrderDataSum: [],
            historyCustomerDataCount: [],
            statisticDateRangesOrderCount: {
                value: '7Days',
                options: DEFAULT_DATE_RANGE_OPTIONS,
            },
            statisticDateRangesOrderSum: {
                value: '7Days',
                options: DEFAULT_DATE_RANGE_OPTIONS,
            },
            statisticDateRangesCustomerCount: {
                value: '7Days',
                options: DEFAULT_DATE_RANGE_OPTIONS,
            },
            isLoading: true,
        };
    },

    computed: {
        orderTrackingRepository() {
            return this.repositoryFactory.create('sales_channel_tracking_order');
        },

        customerTrackingRepository() {
            return this.repositoryFactory.create('sales_channel_tracking_customer');
        },

        currencyFilter() {
            return Shopware.Filter.getByName('currency');
        },

        availableRanges() {
            return DEFAULT_AVAILABLE_RANGES;
        },

        defaultRangeIndex() {
            const index = this.availableRanges.indexOf('7Days');

            return index >= 0 ? index : 0;
        },

        orderCountSummary() {
            return this.historyOrderDataCount.length;
        },

        customerCountSummary() {
            return this.historyCustomerDataCount.length;
        },

        orderSumSummary() {
            const totalTurnover = this.historyOrderDataSum.reduce((turnover, trackingOrder) => {
                return turnover + this.getTrackingOrderAmountTotal(trackingOrder);
            }, 0);

            return this.currencyFilter(totalTurnover, null, 2);
        },

        chartOptionsOrderCount() {
            return this.getChartOptions(this.statisticDateRangesOrderCount);
        },

        chartOptionsCustomerCount() {
            return this.getChartOptions(this.statisticDateRangesCustomerCount);
        },

        chartOptionsOrderSum() {
            return this.getChartOptions(this.statisticDateRangesOrderSum, true);
        },

        orderCountCriteria() {
            const criteria = new Criteria();

            criteria.addAggregation(Criteria.count('count', 'id'));
            criteria.addFilter(Criteria.equals('salesChannelId', this.salesChannel.id));
            criteria.addFilter(
                Criteria.range('createdAt', {
                    gte: this.formatDate(this.dateAgoValue(this.statisticDateRangesOrderCount)),
                }),
            );
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

            return criteria;
        },

        customerCountCriteria() {
            const criteria = new Criteria();

            criteria.addFilter(Criteria.equals('salesChannelId', this.salesChannel.id));
            criteria.addFilter(
                Criteria.range('createdAt', {
                    gte: this.formatDate(this.dateAgoValue(this.statisticDateRangesCustomerCount)),
                }),
            );
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

            return criteria;
        },

        orderSumCriteria() {
            const criteria = new Criteria();

            criteria.addAssociation('order.transactions.stateMachineState');
            criteria.addFilter(Criteria.equals('salesChannelId', this.salesChannel.id));
            criteria.addFilter(Criteria.equals('order.transactions.stateMachineState.technicalName', 'paid'));
            criteria.addFilter(
                Criteria.range('createdAt', {
                    gte: this.formatDate(this.dateAgoValue(this.statisticDateRangesOrderSum)),
                }),
            );
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

            return criteria;
        },

        orderCountSeries() {
            const orderDataArray = this.extractHistoryOrderData(this.historyOrderDataCount);

            if (!orderDataArray) {
                return [];
            }

            return [{ name: this.$t('sw-sales-channel.detail.productExport.insights.numbers'), data: orderDataArray }];
        },

        customerCountSeries() {
            const customerDataArray = this.extractHistoryCustomerData(this.historyCustomerDataCount);

            if (!customerDataArray) {
                return [];
            }

            return [{ name: this.$t('sw-sales-channel.detail.productExport.insights.numbers'), data: customerDataArray }];
        },

        orderSumSeries() {
            const orderDataArray = this.extractTurnoverData(this.historyOrderDataSum);

            if (!orderDataArray) {
                return [];
            }

            return [
                {
                    name: this.$t('sw-sales-channel.detail.productExport.insights.totalTurnover'),
                    data: orderDataArray,
                },
            ];
        },

        today() {
            const today = Shopware.Utils.format.dateWithUserTimezone();
            today.setHours(0, 0, 0, 0);

            return today;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.fetchData();
        },

        async fetchData() {
            try {
                const promises = [];

                if (this.acl.can('sales_channel.viewer')) {
                    promises.push(
                        this.getHistoryOrderCountData(),
                        this.getHistoryOrderSumData(),
                        this.getHistoryCustomerCountData(),
                    );
                }

                await Promise.allSettled(promises);
            } finally {
                this.isLoading = false;
            }
        },

        onOrderCountRangeUpdate(value) {
            this.statisticDateRangesOrderCount.value = value;
            this.getHistoryOrderCountData();
        },

        onCustomerCountRangeUpdate(value) {
            this.statisticDateRangesCustomerCount.value = value;
            this.getHistoryCustomerCountData();
        },

        onOrderSumRangeUpdate(value) {
            this.statisticDateRangesOrderSum.value = value;
            this.getHistoryOrderSumData();
        },

        getHistoryOrderCountData() {
            return this.orderTrackingRepository.search(this.orderCountCriteria).then((response) => {
                this.historyOrderDataCount = response;
            });
        },

        getHistoryCustomerCountData() {
            return this.customerTrackingRepository.search(this.customerCountCriteria).then((response) => {
                this.historyCustomerDataCount = response;
            });
        },

        getHistoryOrderSumData() {
            return this.orderTrackingRepository.search(this.orderSumCriteria).then((response) => {
                this.historyOrderDataSum = response;
            });
        },

        extractHistoryOrderData(data) {
            return Object.entries(
                this.formatOrderDataArray(data, this.getTimeUnitInterval(this.statisticDateRangesOrderCount) === 'hour'),
            ).map(
                ([
                    key,
                    value,
                ]) => ({ x: Number.parseInt(key, 10), y: value.length }),
            );
        },

        extractHistoryCustomerData(data) {
            return Object.entries(
                this.formatCustomerDataArray(
                    data,
                    this.getTimeUnitInterval(this.statisticDateRangesCustomerCount) === 'hour',
                ),
            ).map(
                ([
                    key,
                    value,
                ]) => ({ x: Number.parseInt(key, 10), y: value.length }),
            );
        },

        extractTurnoverData(data) {
            return Object.entries(
                this.formatOrderDataArray(data, this.getTimeUnitInterval(this.statisticDateRangesOrderSum) === 'hour'),
            ).map(
                ([
                    key,
                    value,
                ]) => ({
                    x: Number.parseInt(key, 10),
                    y: value.reduce((turnover, trackingOrder) => {
                        return turnover + this.getTrackingOrderAmountTotal(trackingOrder);
                    }, 0),
                }),
            );
        },

        formatOrderDataArray(array, groupByHour = false) {
            return array.reduce((tmpSeriesData, trackingOrder) => {
                const trackingDate = trackingOrder.createdAt ?? trackingOrder.order?.orderDateTime;

                return this.aggregateByDateTime(trackingDate, groupByHour, trackingOrder, tmpSeriesData);
            }, {});
        },

        formatCustomerDataArray(array, groupByHour = false) {
            return array.reduce((tmpSeriesData, trackingCustomer) => {
                const trackingDate = trackingCustomer.createdAt ?? trackingCustomer.customer?.createdAt;

                return this.aggregateByDateTime(trackingDate, groupByHour, trackingCustomer, tmpSeriesData);
            }, {});
        },

        getTrackingOrderAmountTotal(trackingOrder) {
            return trackingOrder.order?.amountTotal ?? trackingOrder.amountTotal ?? 0;
        },

        dateAgoValue(range) {
            const date = Shopware.Utils.format.dateWithUserTimezone();
            const selectedDateRange = range.value;
            const dateRange = range.options[selectedDateRange] ?? 0;

            if (selectedDateRange === '24Hours') {
                date.setHours(date.getHours() - dateRange);

                return date;
            }

            date.setDate(date.getDate() - dateRange);
            date.setHours(0, 0, 0, 0);

            return date;
        },

        getTimeUnitInterval(range) {
            const statisticDateRange = range.value;

            if (statisticDateRange === 'yesterday' || statisticDateRange === '24Hours') {
                return 'hour';
            }

            return 'day';
        },

        getChartRangeSubtitle(range) {
            return `${this.formatChartHeadlineDate(this.dateAgoValue(range))}-${this.formatChartHeadlineDate(this.today)}`;
        },

        formatDate(date) {
            return Shopware.Utils.format.toISODate(date, false);
        },

        formatChartHeadlineDate(date) {
            const lastKnownLang = Shopware.Application.getContainer('factory').locale.getLastKnownLocale();

            return date.toLocaleDateString(lastKnownLang, {
                day: 'numeric',
                month: 'short',
            });
        },

        formatDateStringToAggregationTime(dateString, aggregateByHour = false) {
            if (!dateString) {
                return null;
            }

            const dateTimeComponentsRegex =
                /^(?<date>\d{4}-\d{2}-\d{2})T(?<hour>\d{2}):(?<minSec>\d{2}:\d{2})(?:\.(?<ms>\d{1,3}))?(?<trail>.*)$/;

            if (dateString.match(dateTimeComponentsRegex) === null) {
                return null;
            }

            const aggregationDateTime = aggregateByHour
                ? dateString.replace(dateTimeComponentsRegex, '$<date>T$<hour>:00:00.000$<trail>')
                : dateString.replace(dateTimeComponentsRegex, '$<date>T00:00:00.000$<trail>');

            return Shopware.Utils.format.dateWithUserTimezone(new Date(aggregationDateTime)).getTime();
        },

        aggregateByDateTime(dateTimeString, aggregateByHour, data, aggregationArray = []) {
            const aggregationTime = this.formatDateStringToAggregationTime(dateTimeString, aggregateByHour);

            if (!aggregationArray[aggregationTime]) {
                aggregationArray[aggregationTime] = [];
            }

            aggregationArray[aggregationTime].push(data);

            return aggregationArray;
        },

        getChartOptions(range, useCurrency = false) {
            const yAxisLabelFormatter = useCurrency
                ? (value) => this.currencyFilter(value, null, 2)
                : (value) => Number.parseInt(value, 10);

            return {
                xaxis: {
                    type: 'datetime',
                    min: this.dateAgoValue(range).getTime(),
                    labels: {
                        datetimeUTC: false,
                    },
                },
                yaxis: {
                    min: 0,
                    tickAmount: useCurrency ? 5 : 3,
                    labels: {
                        formatter: yAxisLabelFormatter,
                    },
                },
                tooltip: {
                    x: {
                        formatter: (value) => this.formatTooltipDate(value, range),
                    },
                },
            };
        },

        tooltipDateFormatOptions(range) {
            if (this.getTimeUnitInterval(range) === 'hour') {
                return {
                    day: '2-digit',
                    month: 'short',
                    hour: '2-digit',
                    minute: '2-digit',
                };
            }

            return {
                day: '2-digit',
                month: 'short',
            };
        },

        formatTooltipDate(value, range) {
            const timestamp = Number.parseInt(String(value), 10);

            if (Number.isNaN(timestamp)) {
                return '';
            }

            const lastKnownLocale = Shopware.Application.getContainer('factory').locale.getLastKnownLocale();
            const userTimeZone = Shopware.Store.get('session').currentUser?.timeZone ?? 'UTC';

            return new Intl.DateTimeFormat(lastKnownLocale, {
                timeZone: userTimeZone,
                ...this.tooltipDateFormatOptions(range),
            }).format(new Date(timestamp));
        },
    },
};
