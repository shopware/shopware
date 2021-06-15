import template from './sw-order-create-details-footer.html.twig';

const { Component, State, Service } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-order-create-details-footer', {
    template,

    inject: ['feature'],

    props: {
        // FIXME: add required attribute and or default value
        // eslint-disable-next-line vue/require-default-prop
        customer: {
            type: Object,
        },

        isCustomerActive: {
            type: Boolean,
            default: false,
        },

        // FIXME: add required attribute and or default value
        // eslint-disable-next-line vue/require-default-prop
        cart: {
            type: Object,
        },
    },

    computed: {
        context: {
            get() {
                return this.customer ? this.customer.salesChannel : {};
            },

            set(context) {
                if (this.customer) this.customer.salesChannel = context;
            },
        },

        salesChannelId: {
            get() {
                return this.customer ? this.customer.salesChannelId : null;
            },

            set(salesChannelId) {
                if (this.customer) this.customer.salesChannelId = salesChannelId;
            },
        },

        salesChannelCriteria() {
            const criteria = new Criteria();

            if (this.salesChannelId) {
                criteria.addFilter(Criteria.equals('salesChannels.id', this.salesChannelId));
            }

            return criteria;
        },

        currencyRepository() {
            return Service('repositoryFactory').create('currency');
        },

        currentCurrencyId() {
            return State.getters['swOrder/currencyId'];
        },

        defaultSalesChannel() {
            return State.get('swOrder').defaultSalesChannel;
        },

        isCartTokenAvailable() {
            return State.getters['swOrder/isCartTokenAvailable'];
        },
    },

    watch: {
        context: {
            immediate: true,
            deep: true,
            handler() {
                if (!this.customer || !this.cart.token) {
                    return;
                }

                this.updateContext();

                this.updateOrderContext();
            },
        },

        isCartTokenAvailable: {
            immediate: true,
            handler() {
                if (this.isCartTokenAvailable && this.customer) {
                    this.updateOrderContext();
                }
            },
        },
    },

    methods: {
        updateContext() {
            const contextKeys = ['currencyId', 'languageId', 'shippingMethodId', 'paymentMethodId'];
            contextKeys.forEach((key) => {
                this.context[key] = this.context[key] || this.defaultSalesChannel[key];
            });
        },

        updateOrderContext() {
            State.dispatch('swOrder/updateOrderContext', {
                context: this.context,
                salesChannelId: this.customer.salesChannelId,
                contextToken: this.cart.token,
            }).then(() => {
                // Make sure updateCustomerContext() is run when updateOrderContext() completed
                this.updateCustomerContext();

                if (this.currentCurrencyId !== this.context.currencyId) {
                    this.getCurrency();
                }
            });
        },

        updateCustomerContext() {
            // We do getCart() only when user just changes the order context items. Otherwise, we do updateCustomerContext()
            State.dispatch('swOrder/updateCustomerContext', {
                customerId: this.customer.id,
                salesChannelId: this.customer.salesChannelId,
                contextToken: this.cart.token,
            }).then((response) => {
                if (response.status === 200) {
                    this.getCart();
                }
            });
        },

        getCart() {
            this.$emit('loading-change', true);

            State.dispatch('swOrder/getCart', {
                salesChannelId: this.customer.salesChannelId,
                contextToken: this.cart.token,
            }).finally(() => {
                this.$emit('loading-change', false);
            });
        },

        getCurrency() {
            return this.currencyRepository.get(this.context.currencyId).then((currency) => {
                State.commit('swOrder/setCurrency', currency);
            });
        },
    },
});
