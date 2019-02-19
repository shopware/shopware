import { Component, State, Mixin } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-customer-detail-order.html.twig';
import './sw-customer-detail-order.scss';

Component.register('sw-customer-detail-order', {
    template,

    mixins: [
        Mixin.getByName('listing')
    ],

    props: {
        customer: {
            type: Object,
            required: true,
            default: {}
        }
    },

    data() {
        return {
            orders: []
        };
    },

    created() {
        this.loadOrders();
    },

    computed: {
        customerOrderStore() {
            return State.getStore('order');
        }
    },

    methods: {
        loadOrders() {
            this.isLoading = true;
            const criteria = CriteriaFactory.equals('orderCustomer.customerId', this.customer.id);
            const params = this.getListingParams();
            params.criteria = criteria;

            this.customerOrderStore.getList(params).then((response) => {
                this.orders = response.items;
            }).finally(() => {
                this.isLoading = false;
            });
        },

        onChange(term) {
            this.term = term;
            this.loadOrders();
        },

        getList() {
            this.loadOrders();
        }
    }
});
