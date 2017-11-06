import PaginationMixin from 'src/app/component/mixin/pagination.mixin';
import ProductListRepository from 'src/core/repository/product.list.repository';
import utils from 'src/core/service/util.service';
import './core-product-list.less';
import template from './core-product-list.twig';

export default Shopware.ComponentFactory.register('core-product-list', {
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
            if (product && product.uuid) {
                this.$router.push({ path: `/core/product/detail/${product.uuid}` });
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
