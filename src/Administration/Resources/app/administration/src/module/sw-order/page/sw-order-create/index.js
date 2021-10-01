import template from './sw-order-create.html.twig';
import './sw-order-create.scss';
import swOrderState from '../../state/order.store';

const { Context, Component, State, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-order-create', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
            showInvalidCodeModal: false,
            showRemindPaymentModal: false,
            remindPaymentModalLoading: false,
            orderId: null,
            orderTransaction: null,
            paymentMethodName: '',
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

        paymentMethodRepository() {
            return this.repositoryFactory.create('payment_method');
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

                return State
                    .dispatch('swOrder/saveOrder', {
                        salesChannelId: this.customer.salesChannelId,
                        contextToken: this.cart.token,
                    })
                    .then((response) => {
                        this.orderId = response?.data?.id;
                        this.orderTransaction = response?.data?.transactions?.[0];

                        this.paymentMethodRepository.get(
                            this.orderTransaction.paymentMethodId,
                            Context.api,
                            new Criteria(1, 1),
                        ).then((paymentMethod) => {
                            this.paymentMethodName = paymentMethod.name;
                        });

                        this.showRemindPaymentModal = true;
                    })
                    .catch((error) => this.showError(error))
                    .finally(() => {
                        this.isLoading = false;
                    });
            }

            if (this.invalidPromotionCodes.length > 0) {
                this.openInvalidCodeModal();
            } else {
                this.showError();
            }

            return Promise.resolve();
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

        onRemindPaymentModalClose() {
            this.isSaveSuccessful = true;

            this.showRemindPaymentModal = false;
        },

        onRemindCustomer() {
            this.remindPaymentModalLoading = true;

            State.dispatch('swOrder/remindPayment', {
                orderTransactionId: this.orderTransaction?.id,
            }).then(() => {
                this.remindPaymentModalLoading = false;

                this.onRemindPaymentModalClose();
            });
        },
    },
});
