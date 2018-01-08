import PaginationMixin from 'src/app/component/mixin/pagination.mixin';
import ProductListRepository from 'src/core/repository/product.list.repository';
import utils from 'src/core/service/util.service';
import './sw-product-list.less';
import template from './sw-product-list.twig';

Shopware.Component.register('sw-product-list', {
    mixins: [PaginationMixin, ProductListRepository],

    data() {
        return {
            isWorking: true,
            productList: [],
            errors: []
        };
    },

    created() {
        this.initProductList().then(() => {
            this.isWorking = false;
        });
    },

    filters: {
        currency: utils.currency
    },

    methods: {
        onEdit(product) {
            if (product && product.id) {
                this.$router.push({ name: 'sw.product.detail', params: { id: product.id } });
            }
        },

        handlePagination(offset, limit) {
            this.isWorking = true;
            this.getProductList(offset, limit).then(() => {
                this.isWorking = false;
            });
        }
    },

    template
});
