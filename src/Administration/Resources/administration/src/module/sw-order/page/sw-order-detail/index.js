import { Component, State } from 'src/core/shopware';
import template from './sw-order-detail.html.twig';
import './sw-order-detail.less';

Component.register('sw-order-detail', {
    template,

    data() {
        return {
            order: {},
            orderId: null
        };
    },

    computed: {
        orderStore() {
            return State.getStore('order');
        },
        lineItemsStore() {
            return this.order.getAssociationStore('lineItems');
        },

        deliveriesStore() {
            return this.order.getAssociationStore('deliveries');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.orderId = this.$route.params.id;
            this.order = this.orderStore.getById(this.orderId);

            this.lineItemsStore.getList({
                page: 1,
                limit: 25
            });

            this.deliveriesStore.getList({
                page: 1,
                limit: 50
            });
        },

        onSave() {
            // TODO: Implement save order
        }
    }
});
