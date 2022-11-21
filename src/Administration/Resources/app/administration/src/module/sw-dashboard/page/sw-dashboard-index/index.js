import template from './sw-dashboard-index.html.twig';
import './sw-dashboard-index.scss';

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
        'feature',
    ],

    data() {
        return {
            /** @deprecated tag:v6.5.0 - Will be removed. Use sw-dashboard-statistics instead */
            historyOrderDataCount: null,
            /** @deprecated tag:v6.5.0 - Will be removed. Use sw-dashboard-statistics instead */
            historyOrderDataSum: null,
            /** @deprecated tag:v6.5.0 - Will be removed. Use sw-dashboard-statistics instead */
            todayOrderData: [],
            /** @deprecated tag:v6.5.0 - Will be removed. Use sw-dashboard-statistics instead */
            todayOrderDataLoaded: false,
            /** @deprecated tag:v6.5.0 - Will be removed. Use sw-dashboard-statistics instead */
            todayOrderDataSortBy: 'orderDateTime',
            /** @deprecated tag:v6.5.0 - Will be removed. Use sw-dashboard-statistics instead */
            todayOrderDataSortDirection: 'DESC',
            cachedHeadlineGreetingKey: null,
            /** @deprecated tag:v6.5.0 - Will be removed. Use sw-dashboard-statistics instead */
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
            /** @deprecated tag:v6.5.0 - Will be removed. Use sw-dashboard-statistics instead */
            isLoading: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        welcomeMessage() {
            const greetingName = this.greetingName;
            const welcomeMessage = this.$tc(
                this.cachedHeadlineGreetingKey,
                1,
                { greetingName },
            );

            // in the headline we want to greet the user by his firstname
            // if his first name is not available, we remove the personalized greeting part
            // but we want to make sure the punctuation like `.`, `!` or `?` is kept
            // for example "Still awake, ?" -> "Still awake?"…
            if (!greetingName) {
                return welcomeMessage.replace(/\,\s*/, '');
            }

            return welcomeMessage;
        },

        welcomeSubline() {
            return this.$tc(this.getGreetingTimeKey('daytimeWelcomeText'));
        },

        greetingName() {
            const { currentUser } = Shopware.State.get('session');

            // if currentUser?.firstName returns a loose falsy value
            // like `""`, `0`, `false`, `null`, `undefined`
            // we want to use `null` in the ongoing process chain,
            // otherwise we would need to take care of `""` and `null`
            // or `undefined` in tests and other places
            return currentUser?.firstName || null;
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed. Use sw-dashboard-statistics instead
         */
        chartOptionsOrderCount() {
            return {
                title: {
                    text: this.$tc('sw-dashboard.monthStats.orderNumber'),
                    style: {
                        fontSize: '16px',
                        fontWeight: '600',
                    },
                },
                xaxis: {
                    type: 'datetime',
                    min: this.dateAgo.getTime(),
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

        /**
         * @deprecated tag:v6.5.0 - Will be removed. Use sw-dashboard-statistics instead
         */
        chartOptionsOrderSum() {
            return {
                xaxis: {
                    type: 'datetime',
                    min: this.dateAgo.getTime(),
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

        /**
         * @deprecated tag:v6.5.0 - Will be removed. Use sw-dashboard-statistics instead.
         */
        orderRepository() {
            return this.repositoryFactory.create('order');
        },

        /**
         * @deprecated tag:v6.5.0 - Will be renamed, use orderCountSeries instead
         * @deprecated tag:v6.5.0 - Will be removed to `sw-dashboard/component/sw-dashboard-statistics`
         */
        orderCountMonthSeries() {
            return this.orderCountSeries;
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed. Use sw-dashboard-statistics instead.
         */
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

        /**
         * @deprecated tag:v6.5.0 - Will be removed. Use sw-dashboard-statistics instead.
         */
        orderCountToday() {
            if (this.todayBucket) {
                return this.todayBucket.count;
            }
            return 0;
        },

        /**
         * @deprecated tag:v6.5.0 - Will be renamed, use orderSumSeries instead
         * @deprecated tag:v6.5.0 - Will be removed. Use sw-dashboard-statistics instead.
         */
        orderSumMonthSeries() {
            return this.orderSumSeries;
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed. Use sw-dashboard-statistics instead.
         */
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

        /**
         * @deprecated tag:v6.5.0 - Will be removed. Use sw-dashboard-statistics instead.
         */
        orderSumToday() {
            if (this.todayBucket) {
                return this.todayBucket.totalAmount.sum;
            }
            return 0;
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed. Use sw-dashboard-statistics instead.
         */
        hasOrderToday() {
            return this.todayOrderData.length > 0;
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed. Use sw-dashboard-statistics instead.
         */
        hasOrderInMonth() {
            return !!this.historyOrderDataCount && !!this.historyOrderDataSum;
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed. Use sw-dashboard-statistics instead.
         */
        dateAgo() {
            const date = Shopware.Utils.format.dateWithUserTimezone();
            const selectedDateRange = this.statisticDateRanges.value;
            const dateRange = this.statisticDateRanges.options[selectedDateRange] ?? 0;

            // special case for "24Hours": return directly because we need hours instead of days
            if (selectedDateRange === '24Hours') {
                date.setHours(date.getHours() - dateRange);

                return date;
            }

            date.setDate(date.getDate() - dateRange);
            date.setHours(0, 0, 0, 0);

            return date;
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed. Use sw-dashboard-statistics instead.
         */
        today() {
            const today = Shopware.Utils.format.dateWithUserTimezone();
            today.setHours(0, 0, 0, 0);
            return today;
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed. Use sw-dashboard-statistics instead.
         */
        todayBucket() {
            return this.calculateTodayBucket(this.historyOrderDataCount);
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed. Use sw-dashboard-statistics instead.
         */
        todayBucketSum() {
            return this.calculateTodayBucket(this.historyOrderDataSum);
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed. Use sw-dashboard-statistics instead.
         */
        getTimeUnitInterval() {
            const statisticDateRange = this.statisticDateRanges.value;

            if (statisticDateRange === 'yesterday' || statisticDateRange === '24Hours') {
                return 'hour';
            }

            return 'day';
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed. Use sw-dashboard-statistics instead.
         */
        systemCurrencyISOCode() {
            return Shopware.Context.app.systemCurrencyISOCode;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        /**
         * @deprecated tag:v6.5.0 - Will be removed. Use sw-dashboard-statistics instead.
         */
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

        /**
         * @deprecated tag:v6.5.0 - won't call getHistoryOrderData nor fetchTodayData after FEATURE_NEXT_18187 is removed
         */
        createdComponent() {
            Shopware.ExtensionAPI.publishData({
                id: 'sw-dashboard-detail__todayOrderData',
                path: 'todayOrderData',
                scope: this,
            });
            Shopware.ExtensionAPI.publishData({
                id: 'sw-dashboard-detail__statisticDateRanges',
                path: 'statisticDateRanges',
                scope: this,
            });
            this.cachedHeadlineGreetingKey = this.cachedHeadlineGreetingKey ?? this.getGreetingTimeKey('daytimeHeadline');

            if (this.feature.isActive('FEATURE_NEXT_18187')) {
                return;
            }

            if (!this.acl.can('order.viewer')) {
                // check if user object is set up, if not recall this function…
                if (Shopware.State.get('session')?.userPending) {
                    // this.$nextTick was blocking whole renderflow, so setTimeout (aka a task) must do the job
                    window.setTimeout(() => {
                        this.createdComponent();
                    }, 0);
                }
                return;
            }

            this.isLoading = true;
            this.getHistoryOrderData();

            this.todayOrderDataLoaded = false;
            this.fetchTodayData().then((response) => {
                this.todayOrderData = response;
                this.todayOrderDataLoaded = true;
                this.isLoading = false;
            });
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed. Use sw-dashboard-statistics instead.
         */
        getHistoryOrderData() {
            this.fetchHistoryOrderDataCount()
                .then((response) => {
                    if (response.aggregations) {
                        this.historyOrderDataCount = response.aggregations.order_count_bucket;
                    }
                });

            this.fetchHistoryOrderDataSum()
                .then((response) => {
                    if (response.aggregations) {
                        this.historyOrderDataSum = response.aggregations.order_sum_bucket;
                    }
                });
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed. Use sw-dashboard-statistics instead.
         */
        fetchHistoryOrderDataCount() {
            const criteria = new Criteria(1, 1);

            criteria.addAggregation(
                Criteria.histogram(
                    'order_count_bucket',
                    'orderDateTime',
                    this.getTimeUnitInterval,
                    null,
                    Criteria.sum('totalAmount', 'amountTotal'),
                    Shopware.State.get('session').currentUser?.timeZone ?? 'UTC',
                ),
            );

            criteria.addFilter(Criteria.range('orderDate', { gte: this.formatDate(this.dateAgo) }));

            return this.orderRepository.search(criteria);
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed. Use sw-dashboard-statistics instead.
         */
        fetchHistoryOrderDataSum() {
            const criteria = new Criteria(1, 1);

            criteria.addAggregation(
                Criteria.histogram(
                    'order_sum_bucket',
                    'orderDateTime',
                    this.getTimeUnitInterval,
                    null,
                    Criteria.sum('totalAmount', 'amountTotal'),
                    Shopware.State.get('session').currentUser?.timeZone ?? 'UTC',
                ),
            );

            criteria.addAssociation('stateMachineState');

            criteria.addFilter(Criteria.equals('transactions.stateMachineState.technicalName', 'paid'));
            criteria.addFilter(Criteria.range('orderDate', { gte: this.formatDate(this.dateAgo) }));

            return this.orderRepository.search(criteria);
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed. Use sw-dashboard-statistics instead.
         */
        fetchTodayData() {
            const criteria = new Criteria(1, 10);

            criteria.addAssociation('currency');

            criteria.addFilter(Criteria.equals('orderDate', this.formatDate(new Date())));
            criteria.addSorting(Criteria.sort(this.todayOrderDataSortBy, this.todayOrderDataSortDirection));

            return this.orderRepository.search(criteria);
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed. Use sw-dashboard-statistics instead.
         */
        formatDate(date) {
            return Shopware.Utils.format.toISODate(date, false);
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed. Use sw-dashboard-statistics instead.
         */
        formatChartHeadlineDate(date) {
            const lastKnownLang = Shopware.Application.getContainer('factory').locale.getLastKnownLocale();

            return date.toLocaleDateString(lastKnownLang, {
                day: 'numeric',
                month: 'short',
            });
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed. Use sw-dashboard-statistics instead.
         */
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

        /**
         * @deprecated tag:v6.5.0 - Will be removed. Use sw-dashboard-statistics instead.
         */
        getVariantFromOrderState(order) {
            return this.stateStyleDataProviderService.getStyle('order.state', order.stateMachineState.technicalName).variant;
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed. Use sw-dashboard-statistics instead.
         */
        parseDate(date) {
            const parsedDate = new Date(date.replace(/-/g, '/').replace('T', ' ').replace(/\..*|\+.*/, ''));
            return parsedDate.valueOf();
        },

        /**
         * getGreetingTimeKey reads through the existing dictionary and returns a localtime aware
         * `$tc ()` compatible String. The timebased dictionary keys look like `5h` or `11h` or `16h`
         * and contains an array with different greeting messages.
         * @param {String} type either 'daytimeHeadline' or 'daytimeWelcomeText'
         * @returns {String}
         */
        getGreetingTimeKey(type = 'daytimeHeadline') {
            const translateKey = `sw-dashboard.introduction.${type}`;
            const greetings = this.getGreetings(type);
            const hourNow = new Date().getHours();

            if (greetings === undefined) {
                return '';
            }

            // to find the right timeslot, we user array.find() which will stop after first match
            // for that reason the greetingTimes must be ordered from latest to earliest hour
            const greetingTimes = Object.keys(greetings)
                .map(entry => parseInt(entry.replace('h', ''), 10))
                .sort((a, b) => a - b)
                .reverse();

            /* find the current time slot */
            const greetingTime = greetingTimes.find(time => hourNow >= time) || greetingTimes[0];
            const greetingIndex = Math.floor(Math.random() * greetings[`${greetingTime}h`].length);

            return `${translateKey}.${greetingTime}h[${greetingIndex}]`;
        },

        getGreetings(type = 'daytimeHeadline') {
            const i18nMessages = this.$i18n.messages;

            const localeGreetings = i18nMessages?.[this.$i18n.locale]?.['sw-dashboard']?.introduction?.[type];
            const fallbackGreetings = i18nMessages?.[this.$i18n.fallbackLocale]?.['sw-dashboard']?.introduction?.[type];

            return localeGreetings ?? fallbackGreetings;
        },
    },
};
