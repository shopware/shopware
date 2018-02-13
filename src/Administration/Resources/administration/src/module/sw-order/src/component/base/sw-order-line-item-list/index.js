import { Component } from 'src/core/shopware';
import PaginationMixin from 'src/app/component/mixin/pagination.mixin';
import template from './sw-order-line-item-list.html.twig';

Component.register('core-order-line-item-list', {
    inject: ['orderLineItemService'],
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
            lineItems: [],
            errors: []
        };
    },

    watch: {
        order: 'getData'
    },

    methods: {
        getData() {
            this.getOrderItemsList();
        },

        getOrderItemsList(offset = this.offset, limit = this.limit) {
            this.isWorking = true;
            this.orderLineItemService
                .getList(offset, limit, this.order.id)
                .then((response) => {
                    this.lineItems = response.data;
                    this.errors = response.errors;
                    this.total = response.total;
                    this.isWorking = false;
                });
        },

        handlePagination(offset, limit) {
            this.getOrderItemsList(offset, limit);
        }
    },
    template
});
