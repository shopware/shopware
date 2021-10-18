import template from './sw-dashboard-index.html.twig';
import './sw-dashboard-index.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-dashboard-index', {
    template,

    inject: ['repositoryFactory', 'stateStyleDataProviderService', 'acl'],

    data() {
        return {
            historyOrderData: null,
            todayOrderData: [],
            todayOrderDataLoaded: false,
            cachedHeadlineGreetingKey: null,
            statisticDateRanges: {
                value: '30Days',
                options: {
                    '30Days': 30,
                    '14Days': 14,
                    '7Days': 7,
                    yesterday: 1,
                    '24Hours': 24,
                },
            },
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

        chartOptionsOrderSum() {
            return {
                title: {
                    text: this.$tc('sw-dashboard.monthStats.turnover'),
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

        /**
         * @deprecated tag:v6.5.0 - Will be renamed, use orderCountSeries instead
         */
        orderCountMonthSeries() {
            return this.orderCountSeries;
        },

        orderCountSeries() {
            if (!this.historyOrderData) {
                return [];
            }

            // format data for chart
            const seriesData = this.historyOrderData.buckets.map((data) => {
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

        /**
         * @deprecated tag:v6.5.0 - Will be renamed, use orderSumSeries instead
         */
        orderSumMonthSeries() {
            return this.orderSumSeries;
        },

        orderSumSeries() {
            if (!this.historyOrderData) {
                return [];
            }

            // format data for chart
            const seriesData = this.historyOrderData.buckets.map((data) => {
                return { x: this.parseDate(data.key), y: data.totalAmount.sum };
            });

            // add empty value for today if there isn't any order, otherwise today would be missing
            if (!this.todayBucket) {
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
            return !!this.historyOrderData;
        },

        dateAgo() {
            const date = new Date();
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

        today() {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            return today;
        },

        todayBucket() {
            if (!this.historyOrderData) {
                return null;
            }

            // search for stats with same timestamp as today
            const findDateStats = this.historyOrderData.buckets.find((dateCount) => {
                // when date exists
                if (dateCount.key) {
                    const timeConverted = this.parseDate(dateCount.key);

                    const todayStart = new Date().setHours(0, 0, 0, 0);
                    const todayEnd = new Date().setHours(24, 59, 59, 59);

                    // if time is today
                    return timeConverted >= todayStart && timeConverted <= todayEnd;
                }

                return false;
            });

            if (findDateStats) {
                return findDateStats;
            }
            return null;
        },

        getTimeUnitInterval() {
            const statisticDateRange = this.statisticDateRanges.value;

            if (statisticDateRange === 'yesterday' || statisticDateRange === '24Hours') {
                return 'hour';
            }

            return 'day';
        },

        systemCurrencyISOCode() {
            return Shopware.Context.app.systemCurrencyISOCode;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            // cache personalized greeting key to avoid headline swap
            this.cachedHeadlineGreetingKey = this.cachedHeadlineGreetingKey ?? this.getGreetingTimeKey('daytimeHeadline');

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

            this.getHistoryOrderData();

            this.todayOrderDataLoaded = false;
            this.fetchTodayData().then((response) => {
                this.todayOrderData = response;
                this.todayOrderDataLoaded = true;
            });
        },

        getHistoryOrderData() {
            this.fetchHistoryOrderData().then((response) => {
                if (response.aggregations) {
                    this.historyOrderData = response.aggregations.order_count_month;
                }
            });
        },

        fetchHistoryOrderData() {
            const criteria = new Criteria(1, 10);
            criteria.setLimit(1);

            criteria.addAggregation(
                Criteria.histogram(
                    'order_count_month',
                    'orderDateTime',
                    this.getTimeUnitInterval,
                    null,
                    Criteria.sum('totalAmount', 'amountTotal'),
                    Shopware?.State?.get('session')?.currentUser?.timeZone ?? 'UTC',
                ),
            );

            // add filter for last 30 days
            criteria.addFilter(Criteria.range('orderDate', { gte: this.formatDate(this.dateAgo) }));

            return this.orderRepository.search(criteria);
        },

        fetchTodayData() {
            const criteria = new Criteria(1, 10);

            criteria.addAssociation('currency');
            // add filter for last 30 days
            criteria.addFilter(Criteria.range('orderDate', { gte: this.formatDate(this.today) }));
            criteria.addSorting(Criteria.sort('orderDateTime', 'ASC'));

            return this.orderRepository.search(criteria);
        },

        formatDate(date) {
            return Shopware.Utils.format.toISODate(date, false);
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
});
