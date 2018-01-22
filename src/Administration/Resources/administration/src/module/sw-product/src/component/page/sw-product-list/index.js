import { Component, Mixin } from 'src/core/shopware';
import PaginationMixin from 'src/app/component/mixin/pagination.mixin';
import template from './sw-product-list.twig';
import './sw-product-list.less';

Component.register('sw-product-list', {
    mixins: [
        PaginationMixin,
        Mixin.getByName('productList')
    ],

    methods: {
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

        handlePagination(offset, limit) {
            this.offset = offset;
            this.limit = limit;

            this.getProductList({
                limit,
                offset
            });
        }
    },

    template
});
