import { Component, Mixin } from 'src/core/shopware';
import PaginationMixin from 'src/app/component/mixin/pagination.mixin';
import template from './sw-customer-list.html.twig';
import './sw-customer-list.less';

Component.register('sw-customer-list', {
    template,

    mixins: [
        PaginationMixin,
        Mixin.getByName('customerList')
    ],

    created() {
        this.getDataFromRoute();
        this.updateRoute();
    },

    methods: {
        updateRoute() {
            const params = this.getListingParams();

            this.$router.push({
                name: 'sw.customer.index',
                params
            });
        },

        handlePagination() {
            this.updateRoute();
            this.getCustomerList();
        },

        onSearch(value) {
            this.term = value;

            this.updateRoute();
            this.getCustomerList();
        },

        onRefresh() {
            this.getCustomerList();
        },

        onSortColumn(column) {
            if (this.sortBy === column.dataIndex) {
                this.sortDirection = (this.sortDirection === 'ASC' ? 'DESC' : 'ASC');
            } else {
                this.sortBy = column.dataIndex;
                this.sortDirection = 'ASC';
            }

            this.updateRoute();
            this.getCustomerList();
        },

        onInlineEditSave(opts) {
            this.isLoading = true;

            return this.$store.dispatch('customer/saveCustomer', opts.item).then(() => {
                this.isLoading = false;
            }).catch(() => {
                this.isLoading = false;
            });
        }
    }
});
