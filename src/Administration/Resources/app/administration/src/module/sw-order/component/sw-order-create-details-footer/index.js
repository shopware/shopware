import template from './sw-order-create-details-footer.html.twig';

const { Component, State, Service } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-order-create-details-footer', {
    template,

    props: {
        customer: {
            type: Object
        },

        isCustomerActive: {
            type: Boolean,
            default: false
        },

        cart: {
            type: Object
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
        }
    },

    watch: {
        context: {
            immediate: true,
            deep: true,
            handler() {
                if (!this.customer) return;

                this.updateContext();

                State.dispatch('swOrder/updateOrderContext', {
                    context: this.context,
                    salesChannelId: this.customer.salesChannelId,
                    contextToken: this.cart.token
                }).then(() => {
                    if (this.context.currencyId && this.currentCurrencyId !== this.context.currencyId) {
                        this.currencyRepository
                            .get(this.context.currencyId, Shopware.Context.api)
                            .then((currency) => {
                                State.commit('swOrder/setCurrency', currency);
                            });
                    }

                    if (!this.cart.token || this.cart.lineItems.length === 0) return;

                    this.$emit('loading-change', true);

                    State.dispatch('swOrder/getCart', {
                        salesChannelId: this.customer.salesChannelId,
                        contextToken: this.cart.token
                    })
                        .finally(() => this.$emit('loading-change', false));
                });
            }
        }
    },

    methods: {
        updateContext() {
            const contextKeys = ['currencyId', 'languageId', 'shippingMethodId', 'paymentMethodId'];
            contextKeys.forEach((key) => {
                this.context[key] = this.context[key] || this.defaultSalesChannel[key];
            });
        }
    }
});
