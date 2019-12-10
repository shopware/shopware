import template from './sw-order-create.html.twig';
import './sw-order-create.scss';

const { Component, State } = Shopware;

Component.register('sw-order-create', {
    template,

    computed: {
        customer() {
            return State.get('swOrder').customer || {};
        },

        cart() {
            return State.get('swOrder').cart;
        }
    },

    created() {
        this.createdComponent();
    },

    beforeDestroy() {
        this.beforeDestroyedComponent();
    },

    methods: {
        createdComponent() {
            if (!this.next5515) {
                this.redirectToOrderList();
            }
        },

        beforeDestroyedComponent() {
            this.removeCustomer();
        },

        removeCustomer() {
            Shopware.State.commit('swOrder/removeCustomer');
        },

        redirectToOrderList() {
            this.$router.push({ name: 'sw.order.index' });
        },

        onSaveOrder() {
            State.dispatch('swOrder/saveOrder', {
                salesChannelId: this.customer.salesChannelId,
                contextToken: this.cart.token
            }).then(() => {
                this.removeCustomer();
                this.redirectToOrderList();
            });
        },

        onCancelOrder() {
            State.dispatch('swOrder/cancelOrder').then(() => {
                this.removeCustomer();
                this.redirectToOrderList();
            });
        }
    }
});
