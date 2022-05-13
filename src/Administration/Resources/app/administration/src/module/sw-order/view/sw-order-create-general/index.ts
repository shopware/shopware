import template from './sw-order-create-general.html.twig';
import type {
    CalculatedTax,
    CartDelivery,
    LineItem,
    Cart,
    Currency,
    Customer,
    PromotionCodeTag,
    SalesChannelContext,
} from '../../order.types';

const { Component, State, Mixin, Utils } = Shopware;
const { get, format, array } = Utils;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Component.wrapComponentConfig({
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('cart-notification'),
    ],

    data(): {
        isLoading: boolean,
        } {
        return {
            isLoading: false,
        };
    },

    computed: {
        customer(): Customer | null {
            return State.get('swOrder').customer;
        },

        cart(): Cart {
            return State.get('swOrder').cart;
        },

        currency(): Currency {
            return State.get('swOrder').context.currency;
        },

        context(): SalesChannelContext {
            return State.get('swOrder').context;
        },

        isCustomerActive(): boolean {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            return State.getters['swOrder/isCustomerActive'] as boolean;
        },

        cartDelivery(): CartDelivery {
            return get(this.cart, 'deliveries[0]', null) as CartDelivery;
        },

        cartDeliveryDiscounts(): CartDelivery[] {
            return array.slice(this.cart.deliveries, 1) || [];
        },

        taxStatus(): string {
            return get(this.cart, 'price.taxStatus', '') as string;
        },

        shippingCostsDetail(): string | null {
            if (!this.cartDelivery) {
                return null;
            }

            const calcTaxes = this.sortByTaxRate(this.cartDelivery.shippingCosts.calculatedTaxes);
            const decorateCalcTaxes = calcTaxes.map((item: CalculatedTax) => {
                return this.$tc('sw-order.createBase.shippingCostsTax', 0, {
                    taxRate: item.taxRate,
                    tax: format.currency(item.tax, this.currency.shortName, this.currency.totalRounding.decimals),
                });
            });

            return `${this.$tc('sw-order.createBase.tax')}<br>${decorateCalcTaxes.join('<br>')}`;
        },

        filteredCalculatedTaxes(): CalculatedTax[] {
            if (!this.cart.price || !this.cart.price.calculatedTaxes) {
                return [];
            }

            return this.sortByTaxRate(this.cart.price.calculatedTaxes ?? [])
                .filter((price: CalculatedTax) => price.tax !== 0);
        },

        displayRounded(): boolean {
            if (!this.cart.price) {
                return false;
            }

            return this.cart.price.rawTotal !== this.cart.price.totalPrice;
        },

        orderTotal(): number {
            if (!this.cart.price) {
                return 0;
            }

            if (this.displayRounded) {
                return this.cart.price.rawTotal;
            }

            return this.cart.price.totalPrice;
        },
    },

    created(): void {
        this.createdComponent();
    },

    methods: {
        createdComponent(): void {
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

        onSaveItem(item: LineItem): Promise<void> {
            this.isLoading = true;

            // eslint-disable-next-line @typescript-eslint/no-unsafe-return
            return State.dispatch('swOrder/saveLineItem', {
                salesChannelId: this.customer?.salesChannelId,
                contextToken: this.cart.token,
                item,
            }).finally(() => {
                this.isLoading = false;
            });
        },

        onShippingChargeEdited(amount: number): void {
            const positiveAmount = Math.abs(amount);
            this.cartDelivery.shippingCosts.unitPrice = positiveAmount;
            this.cartDelivery.shippingCosts.totalPrice = positiveAmount;
            this.isLoading = true;

            State.dispatch('swOrder/modifyShippingCosts', {
                salesChannelId: this.customer?.salesChannelId,
                contextToken: this.cart.token,
                shippingCosts: this.cartDelivery.shippingCosts,
            }).catch((error) => {
                this.$emit('error', error);
            }).finally(() => {
                this.isLoading = false;
            });
        },

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
                        const removedTag = State.get('swOrder').promotionCodes
                            .find((tag: PromotionCodeTag) => tag.discountId === key);

                        if (removedTag) {
                            State.commit('swOrder/setPromotionCodes', State.get('swOrder').promotionCodes
                                .filter((item: PromotionCodeTag) => {
                                    return item.discountId !== removedTag.discountId;
                                }));
                        }
                    });
                }).finally(() => {
                    this.isLoading = false;
                });
        },

        loadCart(): Promise<void> {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-return
            return State.dispatch('swOrder/getCart', {
                salesChannelId: this.customer?.salesChannelId,
                contextToken: this.cart.token,
            });
        },

        sortByTaxRate(price: Array<CalculatedTax>): Array<CalculatedTax> {
            return price.sort((prev: CalculatedTax, current: CalculatedTax) => {
                return prev.taxRate - current.taxRate;
            });
        },
    },
});
