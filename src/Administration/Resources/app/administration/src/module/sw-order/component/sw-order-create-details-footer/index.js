import template from './sw-order-create-details-footer.html.twig';

const { Component, State } = Shopware;

Component.register('sw-order-create-details-footer', {
    template,

    props: {
        customer: {
            type: Object,
            default: {}
        },

        isCustomerActive: {
            type: Boolean,
            default: false
        },

        cart: {
            type: Object,
            default: {}
        }
    },

    computed: {
        context: {
            get() {
                return this.customer ? this.customer.salesChannel : {};
            },

            set(context) {
                if (this.customer) this.customer.salesChannel = context;
            }
        },

        salesChannelId: {
            get() {
                return this.customer ? this.customer.salesChannelId : null;
            },

            set(salesChannelId) {
                if (this.customer) this.customer.salesChannelId = salesChannelId;
            }
        }
    },

    watch: {
        context: {
            immediate: true,
            deep: true,
            handler() {
                if (this.customer === null) return;

                State.dispatch('swOrder/updateOrderContext', {
                    context: this.context,
                    salesChannelId: this.customer.salesChannelId,
                    contextToken: this.cart.token
                });
            }
        }
    }
});
