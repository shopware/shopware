import template from './sw-order-create.html.twig';
import './sw-order-create.scss';
import swOrderState from '../../state/order.store';

const { Component, State, Mixin } = Shopware;

Component.register('sw-order-create', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    computed: {
        customer() {
            return State.get('swOrder').customer;
        },

        cart() {
            return State.get('swOrder').cart;
        },

        isSaveOrderValid() {
            return this.customer && this.cart.token && this.cart.lineItems.length;
        }
    },

    beforeCreate() {
        State.registerModule('swOrder', swOrderState);
    },

    created() {
        this.createdComponent();
    },

    beforeDestroy() {
        this.unregisterModule();
    },

    methods: {
        createdComponent() {
            this.checkFlagActive();
        },

        unregisterModule() {
            State.unregisterModule('swOrder');
        },

        checkFlagActive() {
            if (!this.next5515) this.redirectToOrderList();
        },

        redirectToOrderList() {
            this.$router.push({ name: 'sw.order.index' });
        },

        onSaveOrder() {
            if (this.isSaveOrderValid) {
                State
                    .dispatch('swOrder/saveOrder', {
                        salesChannelId: this.customer.salesChannelId,
                        contextToken: this.cart.token
                    })
                    .then((response) => {
                        const orderId = response.data.data.id;
                        this.$router.push({ name: 'sw.order.detail', params: { id: orderId } });
                    })
                    .catch(() => this.showError());
            } else {
                this.showError();
            }
        },

        onCancelOrder() {
            State
                .dispatch('swOrder/cancelOrder')
                .then(() => this.redirectToOrderList());
        },

        showError() {
            this.createNotificationError({
                title: this.$tc('sw-order.create.titleSaveError'),
                message: this.$tc('sw-order.create.messageSaveError')
            });
        }
    }
});
