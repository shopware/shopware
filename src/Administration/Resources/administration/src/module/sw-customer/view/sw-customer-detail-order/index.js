import { Component } from 'src/core/shopware';
import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-customer-detail-order.html.twig';
import './sw-customer-detail-order.scss';

Component.register('sw-customer-detail-order', {
    template,

    inject: [
        'repositoryFactory',
        'context'
    ],

    props: {
        customer: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            isLoading: false,
            activeCustomer: this.customer,
            orders: null,
            term: '',
            // todo after NEXT-2291: to be removed if new emptyState-Splashscreens are implemented
            orderIcon: 'default-shopping-paper-bag'
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {
        orderColumns() {
            return this.getOrderColumns();
        },

        orderRepository() {
            return this.repositoryFactory.create('order');
        },

        emptyTitle() {
            return this.term ?
                this.$tc('sw-customer.detailOrder.emptySearchTitle') :
                this.$tc('sw-customer.detailOrder.emptyTitle');
        }
    },

    methods: {
        createdComponent() {
            this.isLoading = true;

            this.refreshList();
        },

        onChange(term) {
            this.term = term;
            this.orders.criteria.setPage(1);
            this.orders.criteria.setTerm(term);

            this.refreshList();
        },

        getOrderColumns() {
            return [{
                property: 'orderNumber',
                dataIndex: 'orderNumber',
                label: this.$tc('sw-customer.detailOrder.columnNumber'),
                align: 'center'
            }, {
                property: 'amountTotal',
                dataIndex: 'amountTotal',
                label: this.$tc('sw-customer.detailOrder.columnAmount'),
                align: 'right'
            }, {
                property: 'stateMachineState.name',
                dataIndex: 'stateMachineState.name',
                label: this.$tc('sw-customer.detailOrder.columnOrderState')
            }, {
                property: 'createdAt',
                dataIndex: 'createdAt',
                label: this.$tc('sw-customer.detailOrder.columnOrderDate'),
                align: 'center'
            }];
        },

        refreshList() {
            let criteria = new Criteria();
            if (!this.orders || !this.orders.criteria) {
                criteria.addFilter(Criteria.equals('order.orderCustomer.customerId', this.customer.id));
            } else {
                criteria = this.orders.criteria;
            }

            this.orderRepository.search(criteria, this.context).then((orders) => {
                this.orders = orders;
                this.isLoading = false;
            });
        }
    }
});
