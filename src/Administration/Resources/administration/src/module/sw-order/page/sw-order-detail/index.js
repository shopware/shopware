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
            deliveries: []
        };
    },

    computed: {
        orderStore() {
            return State.getStore('order');
        },

        lineItemStore() {
            return State.getStore('order_line_item');
        },

        deliveryStory() {
            return State.getStore('order_delivery');
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
            }
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

            this.deliveryStory.getList(params).then((response) => {
                this.deliveries = response.items;
            });
        },

        onSave() {
            // TODO: Implement save order
        }
    }
});
