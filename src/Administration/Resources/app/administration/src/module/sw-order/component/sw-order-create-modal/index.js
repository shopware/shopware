import template from './sw-order-create-modal.html.twig';
import './sw-order-create-modal.scss';

const { Component, State } = Shopware;
const { mapState } = Component.getComponentHelper();

Component.register('sw-order-create-modal', {
    template,

    computed: {
        ...mapState('swOrder', ['customer', 'cart']),
    },

    methods: {
        onCloseModal() {
            if (this.customer === null || this.cart === null) {
                this.$emit('modal-close');
                return;
            }

            State.dispatch('swOrder/cancelCart', {
                salesChannelId: this.customer.salesChannelId,
                contextToken: this.cart.token,
            }).then(() => {
                this.$emit('modal-close');
            });
        },

        onPreviewOrder() {
            this.$emit('order-preview');
        },
    },
});
