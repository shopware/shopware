import { Component, State } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-order-detail.html.twig';
import './sw-order-detail.less';

Component.register('sw-order-detail', {
    template,

    data() {
        return {
            order: {},
            orderId: null,
            lineItems: [],
            deliveries: [],
            deliveryPosition: []
        };
    },

    computed: {
        orderStore() {
            return State.getStore('order');
        },

        lineItemStore() {
            return State.getStore('order_line_item');
        },

        deliveryStore() {
            return State.getStore('order_delivery');
        },

        deliveryPositionStore() {
            return State.getStore('order_delivery_position');
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

                this.getLineItems();
                this.getDeliveries();
                this.getDeliveryPosition();
            }
        },

        getDeliveryPosition() {
            const criteria = [];
            const params = {
                limit: 100,
                offset: 0
            };

            criteria.push(CriteriaFactory.term('orderDelivery.orderId', this.orderId));
            params.criteria = CriteriaFactory.nested('AND', ...criteria);

            this.deliveryPositionStore.getList(params).then((response) => {
                this.deliveryPosition = response.items;
            });
        },

        getLineItems() {
            const criteria = [];
            const params = {
                limit: 100,
                offset: 0
            };

            criteria.push(CriteriaFactory.term('orderId', this.orderId));
            params.criteria = CriteriaFactory.nested('AND', ...criteria);

            this.lineItemStore.getList(params).then((response) => {
                this.lineItems = response.items;
            });
        },

        getDeliveries() {
            const criteria = [];
            const params = {
                limit: 100,
                offset: 0
            };

            criteria.push(CriteriaFactory.term('orderId', this.orderId));
            params.criteria = CriteriaFactory.nested('AND', ...criteria);

            this.deliveryStore.getList(params).then((response) => {
                this.deliveries = response.items;
            });
        },

        onSave() {
            // TODO: Implement save order
        }
    }
});
