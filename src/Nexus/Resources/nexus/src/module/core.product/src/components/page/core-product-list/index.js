import PaginationMixin from 'src/app/component/mixin/pagination.mixin';
import utils from 'src/core/service/util.service';
import './core-product-list.less';
import template from './core-product-list.twig';

export default Shopware.ComponentFactory.register('core-product-list', {
    inject: ['productRepository'],
    mixins: [PaginationMixin],

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
        this.getData();
    },

    filters: {
        currency: utils.currency
    },

    methods: {
        getData() {
            this.isWorking = true;
            this.productRepository
                .getList(this.limit, this.offset)
                .then((listData) => {
                    this.productListProxy = listData.listProxy;
                    this.productList = listData.listProxy.data;
                    this.total = listData.total;
                    this.errors = listData.errors;
                    this.isWorking = false;
                });
        }
    },
    template
});
