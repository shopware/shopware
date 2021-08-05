import template from './sw-order-create.html.twig';
import './sw-order-create.scss';
import swOrderState from '../../state/order.store';

const { Component, State, Mixin } = Shopware;

Component.register('sw-order-create', {
    template,

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
            orderId: null,
            showInvalidCodeModal: false,
        };
    },

    computed: {
        customer() {
            return State.get('swOrder').customer;
        },

        cart() {
            return State.get('swOrder').cart;
        },

        invalidPromotionCodes() {
            return State.getters['swOrder/invalidPromotionCodes'];
        },

        isSaveOrderValid() {
            return this.customer &&
                this.cart.token &&
                this.cart.lineItems.length &&
                !this.invalidPromotionCodes.length;
        },
    },

    beforeCreate() {
        State.registerModule('swOrder', swOrderState);
    },

    beforeDestroy() {
        this.unregisterModule();
    },

    methods: {
        unregisterModule() {
            State.unregisterModule('swOrder');
        },

        redirectToOrderList() {
            this.$router.push({ name: 'sw.order.index' });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({ name: 'sw.order.detail', params: { id: this.orderId } });
        },

        onSaveOrder() {
            if (this.isSaveOrderValid) {
                this.isLoading = true;
                this.isSaveSuccessful = false;

                State
                    .dispatch('swOrder/saveOrder', {
                        salesChannelId: this.customer.salesChannelId,
                        contextToken: this.cart.token,
                    })
                    .then((response) => {
                        this.isSaveSuccessful = true;
                        this.orderId = response?.data?.id;
                    })
                    .catch((error) => this.showError(error))
                    .finally(() => {
                        this.isLoading = false;
                    });
            } else if (this.invalidPromotionCodes.length > 0) {
                this.openInvalidCodeModal();
            } else {
                this.showError();
            }
        },

        onCancelOrder() {
            if (this.customer === null || this.cart === null) {
                this.redirectToOrderList();
                return;
            }

            State
                .dispatch('swOrder/cancelCart', {
                    salesChannelId: this.customer.salesChannelId,
                    contextToken: this.cart.token,
                })
                .then(() => this.redirectToOrderList());
        },

        showError(error) {
            const errorMessage = error?.response?.data?.errors?.[0]?.detail || null;

            this.createNotificationError({
                message: errorMessage || this.$tc('sw-order.create.messageSaveError'),
            });
        },

        openInvalidCodeModal() {
            this.showInvalidCodeModal = true;
        },

        closeInvalidCodeModal() {
            this.showInvalidCodeModal = false;
        },

        removeInvalidCode() {
            State.commit('swOrder/removeInvalidPromotionCodes');
            this.closeInvalidCodeModal();
        },
    },
});
