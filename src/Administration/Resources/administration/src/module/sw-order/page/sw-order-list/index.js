import { Component, Mixin } from 'src/core/shopware';
import PaginationMixin from 'src/app/component/mixin/pagination.mixin';
import template from './sw-order-list.html.twig';
import './sw-order-list.less';

Component.register('sw-order-list', {
    template,

    mixins: [
        PaginationMixin,
        Mixin.getByName('orderList')
    ],

    created() {
        this.getDataFromRoute();
        this.updateRoute();
    },

    methods: {
        updateRoute() {
            const params = this.getListingParams();

            this.$router.push({
                name: 'sw.order.index',
                params
            });
        },

        handlePagination() {
            this.updateRoute();
            this.getOrderList();
        },

        onSearch(value) {
            this.term = value;

            this.updateRoute();
            this.getOrderList();
        },

        onSortColumn(column) {
            if (this.sortBy === column.dataIndex) {
                this.sortDirection = (this.sortDirection === 'ASC' ? 'DESC' : 'ASC');
            } else {
                this.sortBy = column.dataIndex;
                this.sortDirection = 'ASC';
            }

            this.updateRoute();
            this.getOrderList();
        },

        onRefresh() {
            this.getOrderList();
        },

        onEdit(order) {
            if (order && order.id) {
                this.$router.push({
                    name: 'sw.order.detail',
                    params: {
                        id: order.id
                    }
                });
            }
        },

        onInlineEditSave(opts) {
            this.isLoading = true;

            return this.$store.dispatch('order/saveOrder', opts.item).then(() => {
                this.isLoading = false;
            }).catch(() => {
                this.isLoading = false;
            });
        }
    }
});
