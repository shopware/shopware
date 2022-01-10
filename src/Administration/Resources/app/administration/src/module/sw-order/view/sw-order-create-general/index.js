import template from './sw-order-create-general.html.twig';

const { Component, State, Mixin } = Shopware;
const { mapGetters } = Component.getComponentHelper();

Component.register('sw-order-create-general', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('cart-notification'),
        Mixin.getByName('order-cart'),
    ],

    data() {
        return {
            isLoading: false,
        };
    },

    computed: {
        ...mapGetters('swOrder', ['isCustomerActive']),
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.customer) {
                this.$nextTick(() => {
                    this.$router.push({ name: 'sw.order.create.initial' });
                });

                return;
            }

            this.isLoading = true;

            this.loadCart().finally(() => {
                this.isLoading = false;
            });
        },

        onSaveItem(item) {
            this.isLoading = true;

            return State.dispatch('swOrder/saveLineItem', {
                salesChannelId: this.customer.salesChannelId,
                contextToken: this.cart.token,
                item,
            }).finally(() => {
                this.isLoading = false;
            });
        },

        loadCart() {
            return State.dispatch('swOrder/getCart', {
                salesChannelId: this.customer.salesChannelId,
                contextToken: this.cart.token,
            });
        },
    },
});
