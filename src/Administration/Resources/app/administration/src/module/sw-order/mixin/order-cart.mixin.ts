import type { Cart, Currency, Customer, PromotionCodeTag } from '../order.types';

/**
 * @package customer-order
 */

const { Mixin, State } = Shopware;

/**
 * @deprecated tag:v6.5.0 - Will be removed
 * Mixin to handle cart business logic.
 */
Mixin.register('order-cart', {
    data(): {
        isLoading: boolean,
        } {
        return {
            isLoading: false,
        };
    },

    computed: {
        salesChannelId(): string {
            return this.customer?.salesChannelId ?? '';
        },

        promotionCodeTags: {
            get(): PromotionCodeTag[] {
                return State.get('swOrder').promotionCodes;
            },

            set(promotionCodeTags: PromotionCodeTag[]) {
                State.commit('swOrder/setPromotionCodes', promotionCodeTags);
            },
        },

        customer(): Customer | null {
            return State.get('swOrder').customer;
        },

        cart(): Cart {
            return State.get('swOrder').cart;
        },

        currency(): Currency {
            return State.get('swOrder').context.currency;
        },
    },

    methods: {
        onRemoveItems(lineItemKeys: string[]): Promise<void> {
            this.isLoading = true;

            return State.dispatch('swOrder/removeLineItems', {
                salesChannelId: this.customer?.salesChannelId,
                contextToken: this.cart.token,
                lineItemKeys: lineItemKeys,
            })
                .then(() => {
                    // Remove promotion code tag if corresponding line item removed
                    lineItemKeys.forEach(key => {
                        const removedTag = this.promotionCodeTags.find((tag: PromotionCodeTag) => tag.discountId === key);

                        if (removedTag) {
                            this.promotionCodeTags = this.promotionCodeTags.filter((item: PromotionCodeTag) => {
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
