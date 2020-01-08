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

        entitySingleSelectClasses() {
            return [
                'sw-order-create__select',
                'sw-order-create-details-footer__item'
            ];
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
                }).then(() => {
                    this.currencyRepository
                        .get(this.context.currencyId, Shopware.Context.api)
                        .then((currency) => {
                            State.commit('swOrder/setCurrency', currency);
                        });

                    if (this.cart.token === null) return;

                    this.$emit('loading-change', true);

                    State.dispatch('swOrder/getCart', {
                        salesChannelId: this.customer.salesChannelId,
                        contextToken: this.cart.token
                    })
                        .finally(() => this.$emit('loading-change', false));
                });
            }
        }
    }
});
