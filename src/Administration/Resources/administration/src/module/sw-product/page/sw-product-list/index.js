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
        this.getDataFromRoute();
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
        updateRoute() {
            const params = this.getListingParams();

            this.$router.push({
                name: 'sw.product.index',
                params
            });
        },

        handlePagination() {
            this.updateRoute();
            this.getProductList();
        },

        onSearch(value) {
            this.term = value;

            this.updateRoute();
            this.getProductList();
        },

        onSortColumn(column) {
            if (this.sortBy === column.dataIndex) {
                this.sortDirection = (this.sortDirection === 'ASC' ? 'DESC' : 'ASC');
            } else {
                this.sortBy = column.dataIndex;
                this.sortDirection = 'ASC';
            }

            this.updateRoute();
            this.getProductList();
        },

        onSwitchFilter(filter, filterIndex) {
            this.filters[filterIndex].active = !this.filters[filterIndex].active;

            // Switch back to the first page when a filter was enabled / disabled
            this.offset = 0;
            this.getProductList();
        },

        onRefresh() {
            this.getProductList();
        },

        onEdit(product) {
            if (product && product.id) {
                this.$router.push({
                    name: 'sw.product.detail',
                    params: {
                        id: product.id
                    }
                });
            }
        },

        onInlineEditSave(opts) {
            this.isLoading = true;

            return this.$store.dispatch('product/saveProduct', opts.item).then(() => {
                this.isLoading = false;
            }).catch(() => {
                this.isLoading = false;
            });
        }
    }
});
