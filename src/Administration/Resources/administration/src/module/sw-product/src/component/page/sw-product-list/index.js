import { Component, Mixin } from 'src/core/shopware';
import PaginationMixin from 'src/app/component/mixin/pagination.mixin';
import template from './sw-product-list.twig';
import './sw-product-list.less';

Component.register('sw-product-list', {
    template,

    mixins: [
        PaginationMixin,
        Mixin.getByName('productList')
    ],

    data() {
        return {
            filters: [{
                active: false,
                label: 'Preis über 50€',
                criteria: {
                    type: 'range',
                    field: 'product.price',
                    options: {
                        '>': 50
                    }
                }
            }, {
                active: false,
                label: 'Lagerbestand unter 10',
                criteria: {
                    type: 'range',
                    field: 'product.stock',
                    options: {
                        '<': 10
                    }
                }
            }]
        };
    },

    created() {
        this.updateParamsUsingRoute();
        this.updateRoute();
    },

    filters: {
        stockColorVariant(value) {
            if (value > 25) {
                return 'success';
            }
            if (value < 25 && value > 0) {
                return 'warning';
            }

            return 'error';
        }
    },

    methods: {
        onEdit(product) {
            if (product && product.id) {
                this.$router.push({
                    name: 'sw.product.detail.general',
                    params: {
                        id: product.id
                    }
                });
            }
        },

        onSortColumn(column) {
            if (this.sortBy === column.dataIndex) {
                this.sortDirection = (this.sortDirection === 'ASC' ? 'DESC' : 'ASC');
            } else {
                this.sortBy = column.dataIndex;
                this.sortDirection = 'ASC';
            }

            this.updateRoute();

            this.getProductList({
                limit: this.limit,
                offset: this.offset
            });
        },

        handlePagination() {
            this.updateRoute();

            this.getProductList({
                limit: this.limit,
                offset: this.offset
            });
        },

        updateParamsUsingRoute() {
            const routeParams = this.$route.params;

            this.offset = routeParams.offset || this.offset;
            this.limit = routeParams.limit || this.limit;
            this.sortDirection = routeParams.sortDirection || this.sortDirection;
            this.sortBy = routeParams.sortBy || this.sortBy;
            this.term = routeParams.term || this.term;
        },

        updateRoute() {
            const params = {
                limit: this.limit,
                offset: this.offset,
                sortBy: this.sortBy,
                sortDirection: this.sortDirection
            };

            if (this.term && this.term.length) {
                params.term = this.term;
            }

            this.$router.push({
                name: 'sw.product.indexPaginated',
                params
            });
        },

        onSearch(value) {
            this.term = value;

            this.updateRoute();
            this.getProductList({
                limit: this.limit,
                offset: this.offset
            });
        },

        onSwitchFilter(filter, filterIndex) {
            this.filters[filterIndex].active = !this.filters[filterIndex].active;

            // Switch back to the first page when a filter was enabled / disabled
            this.offset = 0;
            this.getProductList({
                limit: this.limit,
                offset: this.offset
            });
        },

        onRefresh() {
            this.getProductList({
                limit: this.limit,
                offset: this.offset
            });
        }
    }
});
