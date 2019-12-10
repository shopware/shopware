import template from './sw-order-create-details-footer.html.twig';

const { Component, State } = Shopware;

Component.register('sw-order-create-details-footer', {
    template,

    props: {
        customer: {
            type: Object,
            required: true
        },

        isCustomerActive: {
            type: Boolean,
            required: true,
            default: false
        },

        cart: {
            type: Object,
            required: true
        }
    },

    computed: {
        context() {
            return this.customer.salesChannel;
        }
    },

    watch: {
        context: {
            immediate: true,
            deep: true,
            handler() {
                if (Object.entries(this.customer).length === 0) return;

                State.dispatch('swOrder/updateOrderContext', {
                    context: this.context,
                    salesChannelId: this.customer.salesChannelId,
                    contextToken: this.cart.token
                });
            }
        }
    }
});
