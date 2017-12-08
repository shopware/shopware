import PaginationMixin from 'src/app/component/mixin/pagination.mixin';
import utils from 'src/core/service/util.service';
import './sw-product-list.less';
import template from './sw-product-list.twig';

Shopware.Component.register('sw-product-list', {
    mixins: [PaginationMixin],

    stateMapping: {
        state: 'productList'
    },

    data() {
        return {
            errors: []
        };
    },

    created() {
        this.receiveProductList({
            limit: this.limit,
            offset: this.offset
        });
    },

    filters: {
        currency: utils.currency
    },

    methods: {
        onEdit(product) {
            if (product && product.id) {
                this.$router.push({
                    name: 'sw.product.detail',
                    params: {
                        uuid: product.uuid
                    }
                });
            }
        },

        handlePagination(offset, limit) {
            this.offset = offset;
            this.limit = limit;

            this.receiveProductList({
                limit,
                offset
            });
        }
    },

    template
});
