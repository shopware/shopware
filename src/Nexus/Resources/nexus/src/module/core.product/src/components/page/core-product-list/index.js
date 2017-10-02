import PaginationMixin from 'src/app/component/mixin/pagination.mixin';
import ProductListRepository from 'src/core/repository/product.list.repository';
import utils from 'src/core/service/util.service';
import './core-product-list.less';
import template from './core-product-list.twig';

export default Shopware.ComponentFactory.register('core-product-list', {
    inject: ['productService'],
    mixins: [ProductListRepository, PaginationMixin],

    data() {
        return {
            limit: 25,
            pageNum: 1,
            isWorking: false,
            productList: [],
            total: 0,
            errors: []
        };
    },

    created() {
        this.initProductList();
    },

    filters: {
        currency: utils.currency
    },

    template
});
