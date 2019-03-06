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
            disableRouteParams: true,
            offset: 0,
            limit: 10,
            paginationSteps: [10, 25, 50, 75, 100],
            orders: []
        };
    },

    created() {
        this.getList();
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
            this.getList();
        },

        getList() {
            if (!this.customer.id) {
                this.$router.push({ name: 'sw.customer.detail.base', params: { id: this.$route.params.id } });
                return;
            }
            this.loadOrders();
        },

        onPageChange(data) {
            this.page = data.page;
            this.limit = data.limit;
            this.getList();
        }
    }
});
