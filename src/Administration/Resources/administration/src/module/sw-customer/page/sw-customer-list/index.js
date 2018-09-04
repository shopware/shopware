import { Component, Mixin } from 'src/core/shopware';
import template from './sw-customer-list.html.twig';
import './sw-customer-list.less';

Component.register('sw-customer-list', {
    template,

    mixins: [
        Mixin.getByName('listing')
    ],

    data() {
        return {
            customers: [],
            isLoading: false
        };
    },

    created() {
        this.$root.$on('search', this.onSearch);
    },

    destroyed() {
        this.$root.$off('search', this.onSearch);
    },

    computed: {
        customerStore() {
            return Shopware.State.getStore('customer');
        }
    },

    methods: {
        onInlineEditSave(customer) {
            this.isLoading = true;

            customer.save().then(() => {
                this.isLoading = false;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        onInlineEditCancel(customer) {
            customer.discardChanges();
        },

        getList() {
            this.isLoading = true;
            const params = this.getListingParams();

            this.customers = [];

            // Use the customer number as the default sorting
            if (!params.sortBy && !params.sortDirection) {
                params.sortBy = 'customerNumber';
                params.sortDirection = 'DESC';
            }

            return this.customerStore.getList(params).then((response) => {
                this.total = response.total;
                this.customers = response.items;
                this.isLoading = false;

                return this.customers;
            });
        }
    }
});
