import template from './sw-order-create.html.twig';
import './sw-order-create.scss';
import swOrderState from '../../state/order.store';

const { Component, State } = Shopware;

Component.register('sw-order-create', {
    template,

    computed: {
        customer() {
            return State.get('swOrder').customer;
        },

        cart() {
            return State.get('swOrder').cart;
        }
    },

    beforeCreate() {
        State.registerModule('swOrder', swOrderState);
    },

    created() {
        this.createdComponent();
    },

    beforeDestroy() {
        this.beforeDestroyedComponent();
    },

    methods: {
        createdComponent() {
            this.checkFlagActive();
        },

        beforeDestroyedComponent() {
            this.removeCustomer();
            this.removeCartToken();
            this.unregisterModule();
        },

        unregisterModule() {
            State.unregisterModule('swOrder');
        },

        checkFlagActive() {
            if (!this.next5515) this.redirectToOrderList();
        },

        removeCustomer() {
            State.commit('swOrder/removeCustomer');
        },

        removeCartToken() {
            State.commit('swOrder/removeCartToken');
        },

        redirectToOrderList() {
            this.$router.push({ name: 'sw.order.index' });
        },

        onSaveOrder() {
            State
                .dispatch('swOrder/saveOrder', {
                    salesChannelId: this.customer.salesChannelId,
                    contextToken: this.cart.token
                })
                .then(() => this.redirectToOrderList());
        },

        onCancelOrder() {
            State
                .dispatch('swOrder/cancelOrder')
                .then(() => this.redirectToOrderList());
        }
    }
});
