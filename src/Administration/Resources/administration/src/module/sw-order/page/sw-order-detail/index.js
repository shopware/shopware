import { Component, State } from 'src/core/shopware';
import template from './sw-order-detail.html.twig';
import './sw-order-detail.scss';

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
            return this.order.getAssociation('lineItems');
        },

        deliveriesStore() {
            return this.order.getAssociation('deliveries');
        }
    },

    created() {
        this.createdComponent();
    },

    watch: {
        '$route.params.id'() {
            this.createdComponent();
        }
    },

    methods: {
        createdComponent() {
            this.orderId = this.$route.params.id;
            this.loadEntityData();
        },

        loadEntityData() {
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

        onChangeLanguage() {
            this.loadEntityData();
        },

        onSave() {
            // TODO: Implement save order
        }
    }
});
