import PaginationMixin from 'src/app/component/mixin/pagination.mixin';
import utils from 'src/core/service/util.service';
import './core-order-list.less';
import template from './core-order-list.twig';

export default Shopware.ComponentFactory.register('core-order-list', {
    inject: ['orderService'],
    mixins: [PaginationMixin],

    data() {
        return {
            limit: 25,
            pageNum: 1,
            isWorking: false,
            orderList: [],
            total: 0,
            errors: []
        };
    },

    created() {
        this.getData();
    },

    filters: {
        currency: utils.currency,
        date: utils.date
    },

    methods: {
        getData() {
            this.isWorking = true;
            this.orderService
                .readAll(this.limit, this.offset)
                .then((response) => {
                    this.orderList = response.data;
                    this.errors = response.errors;
                    this.total = response.total;
                    this.isWorking = false;
                });
        }
    },
    template
});
