const { Mixin, Component, State } = Shopware;
const { mapState } = Component.getComponentHelper();
/**
 * Mixin to handle cart business logic.
 */

Mixin.register('order-cart', {
    data() {
        return {
            isLoading: false,
        };
    },

    computed: {
        salesChannelId() {
            return this.customer?.salesChannelId ?? '';
        },

        promotionCodeTags: {
            get() {
                return State.get('swOrder').promotionCodes;
            },

            set(promotionCodeTags) {
                State.commit('swOrder/setPromotionCodes', promotionCodeTags);
            },
        },


        ...mapState('swOrder', ['currency', 'cart', 'customer']),
    },

    methods: {
        onRemoveItems(lineItemKeys) {
            this.isLoading = true;

            return State.dispatch('swOrder/removeLineItems', {
                salesChannelId: this.customer.salesChannelId,
                contextToken: this.cart.token,
                lineItemKeys: lineItemKeys,
            })
                .then(() => {
                    // Remove promotion code tag if corresponding line item removed
                    lineItemKeys.forEach(key => {
                        const removedTag = this.promotionCodeTags.find(tag => tag.discountId === key);

                        if (removedTag) {
                            this.promotionCodeTags = this.promotionCodeTags.filter(item => {
                                return item.discountId !== removedTag.discountId;
                            });
                        }
                    });
                }).finally(() => {
                    this.isLoading = false;
                });
        },
    },
});
