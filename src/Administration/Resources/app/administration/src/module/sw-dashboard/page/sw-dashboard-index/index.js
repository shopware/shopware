import template from './sw-dashboard-index.html.twig';
import './sw-dashboard-index.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-dashboard-index', {
    template,

    inject: ['repositoryFactory', 'stateStyleDataProviderService'],

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    data() {
        return {
            historyOrderData: null,
            todayOrderData: [],
            todayOrderDataLoaded: false
        };
    },

    computed: {
        chartOptionsOrderCount() {
            return {
                title: { text: this.$tc('sw-dashboard.monthStats.orderNumber') },

                xaxis: { type: 'datetime', min: this.dateAgo.getTime() },
                yaxis: {
                    min: 0,
                    tickAmount: 3,
                    labels: {
                        formatter: (value) => { return parseInt(value, 10); }
                    }
                }
            };
        },

        chartOptionsOrderSum() {
            return {
                title: { text: this.$tc('sw-dashboard.monthStats.turnover') },

                xaxis: { type: 'datetime', min: this.dateAgo.getTime() },
                yaxis: {
                    min: 0,
                    tickAmount: 5,
                    labels: {
                        // price aggregations do not support currencies yet, see NEXT-5069
                        formatter: (value) => this.$options.filters.currency(value, 'EUR')
                    }
                }
            };
        },

        orderRepository() {
            return this.repositoryFactory.create('order');
        },

        orderCountMonthSeries() {
            if (!this.historyOrderData) {
                return [];
            }

            // format data for chart
            const seriesData = this.historyOrderData.buckets.map((data) => {
                return { x: this.parseDate(data.key), y: data.count };
            });

            return [{ name: this.$tc('sw-dashboard.monthStats.numberOfOrders'), data: seriesData }];
        },

        orderCountToday() {
            if (this.todayBucket) {
                return this.todayBucket.count;
            }
            return 0;
        },

        orderSumMonthSeries() {
            if (!this.historyOrderData) {
                return [];
            }

            // format data for chart
            const seriesData = this.historyOrderData.buckets.map((data) => {
                return { x: this.parseDate(data.key), y: data.totalAmount.sum };
            });

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
            // get date 30 days ago
            const date = new Date();
            date.setHours(0, 0, 0, 0);
            date.setDate(date.getDate() - 30);

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

                    // if time is equal to today
                    return timeConverted === this.today.getTime();
                }

                return false;
            });

            if (findDateStats) {
                return findDateStats;
            }
            return null;
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.fetchHistoryOrderData().then((response) => {
                if (response.aggregations) {
                    this.historyOrderData = response.aggregations.order_count_month;
                }
            });

            this.todayOrderDataLoaded = false;
            this.fetchTodayData().then((response) => {
                this.todayOrderData = response;
                this.todayOrderDataLoaded = true;
            });
        },

        fetchHistoryOrderData() {
            const criteria = new Criteria(1, 10);

            criteria.addAggregation(
                Criteria.histogram(
                    'order_count_month',
                    'orderDateTime',
                    'day',
                    null,
                    Criteria.sum('totalAmount', 'amountTotal')
                )
            );

            // add filter for last 30 days
            criteria.addFilter(Criteria.range('orderDate', { gte: this.formatDate(this.dateAgo) }));

            return this.orderRepository.search(criteria, Shopware.Context.api);
        },

        fetchTodayData() {
            const criteria = new Criteria(1, 10);

            criteria.addAssociation('currency');
            // add filter for last 30 days
            criteria.addFilter(Criteria.range('orderDate', { gte: this.formatDate(this.today) }));
            criteria.addSorting(Criteria.sort('orderDateTime', 'ASC'));

            return this.orderRepository.search(criteria, Shopware.Context.api);
        },

        formatDate(date) {
            return `${date.getFullYear()}-${(`0${date.getMonth() + 1}`).slice(-2)}-${date.getDate()}`;
        },

        orderGridColumns() {
            return [{
                property: 'orderNumber',
                label: 'sw-order.list.columnOrderNumber',
                routerLink: 'sw.order.detail',
                allowResize: true,
                primary: true
            }, {
                property: 'orderDateTime',
                dataIndex: 'orderDateTime',
                label: 'sw-dashboard.todayStats.orderTime',
                allowResize: true,
                primary: false
            }, {
                property: 'orderCustomer.firstName',
                dataIndex: 'orderCustomer.firstName,orderCustomer.lastName',
                label: 'sw-order.list.columnCustomerName',
                allowResize: true
            }, {
                property: 'stateMachineState.name',
                label: 'sw-order.list.columnState',
                allowResize: true
            }, {
                property: 'amountTotal',
                label: 'sw-order.list.columnAmount',
                align: 'right',
                allowResize: true
            }];
        },

        getVariantFromOrderState(order) {
            return this.stateStyleDataProviderService.getStyle('order.state', order.stateMachineState.technicalName).variant;
        },

        parseDate(date) {
            const parsedDate = new Date(date.replace(/-/g, '/').replace('T', ' ').replace(/\..*|\+.*/, ''));
            return parsedDate.valueOf();
        }
    }
});
