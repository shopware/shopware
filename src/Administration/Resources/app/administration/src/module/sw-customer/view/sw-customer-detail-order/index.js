import template from './sw-customer-detail-order.html.twig';
import './sw-customer-detail-order.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-customer-detail-order', {
    template,

    inject: ['repositoryFactory'],

    props: {
        customer: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            isLoading: false,
            activeCustomer: this.customer,
            orders: null,
            term: '',
            // todo after NEXT-2291: to be removed if new emptyState-Splashscreens are implemented
            orderIcon: 'default-shopping-paper-bag',
        };
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
        },
    },

    created() {
        this.createdComponent();
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
                label: 'sw-customer.detailOrder.columnNumber',
                align: 'center',
            }, {
                property: 'amountTotal',
                label: 'sw-customer.detailOrder.columnAmount',
                align: 'right',
            }, {
                property: 'stateMachineState.name',
                label: 'sw-customer.detailOrder.columnOrderState',
            }, {
                property: 'orderDateTime',
                label: 'sw-customer.detailOrder.columnOrderDate',
                align: 'center',
            }];
        },

        refreshList() {
            let criteria = new Criteria();
            if (!this.orders || !this.orders.criteria) {
                criteria.addFilter(Criteria.equals('order.orderCustomer.customerId', this.customer.id));
            } else {
                criteria = this.orders.criteria;
            }
            criteria.addAssociation('stateMachineState')
                .addAssociation('currency');

            this.orderRepository.search(criteria).then((orders) => {
                this.orders = orders;
                this.isLoading = false;
            });
        },
    },
});
