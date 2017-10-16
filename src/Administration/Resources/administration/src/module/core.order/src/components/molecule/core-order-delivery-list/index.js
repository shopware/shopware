import PaginationMixin from 'src/app/component/mixin/pagination.mixin';
import utils from 'src/core/service/util.service';
import template from './core-order-delivery-list.html.twig';

export default Shopware.ComponentFactory.register('core-order-delivery-list', {
    inject: ['orderDeliveryService'],
    mixins: [PaginationMixin],

    props: {
        order: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            isWorking: false,
            deliveryList: [],
            errors: []
        };
    },

    watch: {
        order: 'getData'
    },

    filters: {
        currency: utils.currency,
        date: utils.date
    },

    methods: {
        getData() {
            this.getDeliveryList();
        },

        getDeliveryList(offset = this.offset, limit = this.limit) {
            this.isWorking = true;
            this.orderDeliveryService
                .readAll(this.$route.params.uuid, limit, offset)
                .then((response) => {
                    this.deliveryList = response.data;
                    this.errors = response.errors;
                    this.total = response.total;
                    this.isWorking = false;
                });
        },

        handlePagination(offset, limit) {
            this.getDeliveryList(offset, limit);
        }
    },
    template
});
