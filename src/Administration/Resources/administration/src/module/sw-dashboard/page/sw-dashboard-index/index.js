import { Component } from 'src/core/shopware';
import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-dashboard-index.html.twig';
import './sw-dashboard-index.scss';

Component.register('sw-dashboard-index', {
    template,

    inject: ['repositoryFactory', 'context', 'stateStyleDataProviderService'],

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    data() {
        return {
            historyOrderData: {},
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
                        formatter: (value) => this.$options.filters.currency(value)
                    }
                }
            };
        },

        orderRepository() {
            return this.repositoryFactory.create('order');
        },

        orderCountMonthSeries() {
            if (!this.historyOrderData || !this.historyOrderData.order_count_month) {
                return [];
            }

            // format data for chart
            const seriesData = this.historyOrderData.order_count_month.map((data) => {
                return { x: this.parseDate(data.key.orderDate.date), y: data.count };
            });

            return [{ name: this.$tc('sw-dashboard.monthStats.numberOfOrders'), data: seriesData }];
        },

        orderCountToday() {
            if (this.historyOrderData && this.historyOrderData.order_count_month) {
                // search for stats with same timestamp as today
                const findDateStats = this.historyOrderData.order_count_month.find((dateCount) => {
                    // when date exists
                    if (dateCount.key && dateCount.key.orderDate && dateCount.key.orderDate.date) {
                        const timeConverted = this.parseDate(dateCount.key.orderDate.date);

                        // if time is equal to today
                        return timeConverted === this.today.getTime();
                    }

                    return false;
                });

                // return todayStats when found
                if (findDateStats && findDateStats.count) {
                    return findDateStats.count;
                }
            }
            return 0;
        },

        orderSumMonthSeries() {
            if (!this.historyOrderData || !this.historyOrderData.order_sum_month) {
                return [];
            }

            // format data for chart
            const seriesData = this.historyOrderData.order_sum_month.map((data) => {
                return { x: this.parseDate(data.key.orderDate.date), y: data.sum };
            });

            return [{ name: 'Total', data: seriesData }];
        },

        orderSumToday() {
            if (this.historyOrderData && this.historyOrderData.order_sum_month) {
                // search for stats with same timestamp as today
                const findDateStats = this.historyOrderData.order_sum_month.find((dateSum) => {
                    // when date exists
                    if (dateSum.key && dateSum.key.orderDate && dateSum.key.orderDate.date) {
                        const timeConverted = this.parseDate(dateSum.key.orderDate.date);
                        // if time is equal to today
                        return timeConverted === this.today.getTime();
                    }

                    return false;
                });

                // return todayStats when found
                if (findDateStats && findDateStats.sum) {
                    return Math.round(findDateStats.sum);
                }
            }
            return 0;
        },

        hasOrderToday() {
            return this.todayOrderData.length > 0;
        },

        hasOrderInMonth() {
            if (!this.historyOrderData || !this.historyOrderData.order_sum_month) {
                return false;
            }

            return this.historyOrderData.order_sum_month.length > 0;
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
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.fetchHistoryOrderData().then((response) => {
                if (response.aggregations) {
                    this.historyOrderData = response.aggregations;
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

            // order_count_month: get order count for each day in last 30 days
            criteria.addAggregation(Criteria.count('order_count_month', 'id', ['orderDate']));
            // order_sum_month: get totalAmount of orders each day in last 30 days
            criteria.addAggregation(Criteria.sum('order_sum_month', 'amountTotal', ['orderDate']));
            // add filter for last 30 days
            criteria.addFilter(Criteria.range('orderDate', { gte: this.formatDate(this.dateAgo) }));

            return this.orderRepository.search(criteria, this.context);
        },

        fetchTodayData() {
            const criteria = new Criteria(1, 10);

            criteria.addAssociation('currency');
            // add filter for last 30 days
            criteria.addFilter(Criteria.range('orderDate', { gte: this.formatDate(this.today) }));
            criteria.addSorting(Criteria.sort('orderDateTime', 'ASC'));

            return this.orderRepository.search(criteria, this.context);
        },

        formatDate(date) {
            return `${date.getFullYear()}-${(`0${date.getMonth() + 1}`).slice(-2)}-${date.getDate()}`;
        },

        orderGridColumns() {
            return [{
                property: 'orderNumber',
                label: this.$tc('sw-order.list.columnOrderNumber'),
                routerLink: 'sw.order.detail',
                allowResize: true,
                primary: true
            }, {
                property: 'orderDateTime',
                dataIndex: 'orderDateTime',
                label: this.$tc('sw-dashboard.todayStats.orderTime'),
                allowResize: true,
                primary: false
            }, {
                property: 'orderCustomer.firstName',
                dataIndex: 'orderCustomer.firstName,orderCustomer.lastName',
                label: this.$tc('sw-order.list.columnCustomerName'),
                allowResize: true
            }, {
                property: 'stateMachineState.name',
                label: this.$tc('sw-order.list.columnState'),
                allowResize: true
            }, {
                property: 'amountTotal',
                label: this.$tc('sw-order.list.columnAmount'),
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
