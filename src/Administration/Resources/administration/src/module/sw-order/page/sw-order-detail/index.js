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
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.orderId = this.$route.params.id;
                this.order = this.orderStore.getById(this.orderId);
            }
        },

        onSave() {
            // TODO: Implement save order
        }
    }
});
