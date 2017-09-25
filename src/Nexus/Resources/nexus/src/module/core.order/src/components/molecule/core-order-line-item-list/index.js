import PaginationMixin from 'src/app/component/mixin/pagination.mixin';
import utils from 'src/core/service/util.service';
import template from './core-order-line-item-list.html.twig';

export default Shopware.ComponentFactory.register('core-order-line-item-list', {
    inject: ['orderLineItemService'],
    mixins: [PaginationMixin],

    props: {
        order: {
            type: Object,
            required: true,
            default: {}
        }
    },

    data() {
        return {
            limit: 25,
            pageNum: 1,
            isWorking: false,
            lineItemList: [],
            total: 0,
            errors: []
        };
    },

    watch: {
        order: 'getData'
    },

    filters: {
        currency: utils.currency
    },

    methods: {
        getData() {
            this.isWorking = true;
            this.orderLineItemService
                .readAll(this.order.uuid, this.limit, this.offset)
                .then((response) => {
                    this.lineItemList = response.data;
                    this.errors = response.errors;
                    this.total = response.total;
                    this.isWorking = false;
                });
        }
    },
    template
});
