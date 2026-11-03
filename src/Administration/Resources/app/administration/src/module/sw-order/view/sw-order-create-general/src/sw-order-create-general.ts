import template from './sw-order-create-general.html.twig';
import {
    type CalculatedTax,
    type Cart,
    type CartDelivery,
    cartNotificationMixin,
    currency,
    get,
    getCurrencyFilter,
    type LineItem,
    notificationMixin,
    type PromotionCodeTag,
    type SalesChannelContext,
    slice,
    useOrderStore,
    wrapComponentConfig,
} from '../dependencies';

/**
 * @sw-package checkout
 */

/**
 * @public
 */
export default wrapComponentConfig({
    template,

    emits: ['error'],

    mixins: [notificationMixin, cartNotificationMixin],

    data(): {
        isLoading: boolean;
    } {
        return {
            isLoading: false,
        };
    },

    computed: {
        customer(): Entity<'customer'> | null {
            return useOrderStore().customer;
        },

        cart(): Cart {
            return useOrderStore().cart;
        },

        currency(): Entity<'currency'> {
            return useOrderStore().context.currency;
        },

        context(): SalesChannelContext {
            return useOrderStore().context;
        },

        isCustomerActive(): boolean {
            return useOrderStore().isCustomerActive;
        },

        cartDelivery(): CartDelivery {
            return get(this.cart, 'deliveries[0]', null) as CartDelivery;
        },

        cartDeliveryDiscounts(): CartDelivery[] {
            return slice(this.cart.deliveries, 1) || [];
        },

        taxStatus(): string {
            return get(this.cart, 'price.taxStatus', '');
        },

        shippingCostsDetail(): string | null {
            if (!this.cartDelivery) {
                return null;
            }

            const calcTaxes = this.sortByTaxRate(this.cartDelivery.shippingCosts.calculatedTaxes);
            const decorateCalcTaxes = calcTaxes.map((item: CalculatedTax) => {
                return this.$t(
                    'sw-order.createBase.shippingCostsTax',
                    {
                        taxRate: item.taxRate,
                        tax: currency(
                            item.tax,
                            this.currency.isoCode,
                            // eslint-disable-next-line @typescript-eslint/no-unsafe-argument,@typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-explicit-any
                            (this.currency.totalRounding as any)?.decimals,
                        ),
                    },
                    0,
                );
            });

            return `${this.$t('sw-order.createBase.tax')}<br>${decorateCalcTaxes.join('<br>')}`;
        },

        filteredCalculatedTaxes(): CalculatedTax[] {
            if (!this.cart.price || !this.cart.price.calculatedTaxes) {
                return [];
            }

            return this.sortByTaxRate(this.cart.price.calculatedTaxes ?? []).filter(
                (price: CalculatedTax) => price.tax !== 0,
            );
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

        currencyFilter() {
            return getCurrencyFilter();
        },
    },

    created(): void {
        this.createdComponent();
    },

    methods: {
        createdComponent(): void {
            if (!this.customer) {
                void this.$nextTick(() => {
                    void this.$router.push({ name: 'sw.order.create.initial' });
                });

                return;
            }

            this.isLoading = true;

            void this.loadCart().finally(() => {
                this.isLoading = false;
            });
        },

        async onSaveItem(item: LineItem): Promise<void> {
            this.isLoading = true;
            if (!this.customer) return;

            await useOrderStore()
                .saveLineItem({
                    salesChannelId: this.customer.salesChannelId,
                    contextToken: this.cart.token,
                    item,
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onShippingChargeEdited(): void {
            this.isLoading = true;
            if (!this.customer) return;

            useOrderStore()
                .modifyShippingCosts({
                    salesChannelId: this.customer.salesChannelId,
                    contextToken: this.cart.token,
                    shippingCosts: this.cartDelivery.shippingCosts,
                })
                .catch((error) => {
                    this.$emit('error', error);
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        async onRemoveItems(lineItemKeys: string[]): Promise<void> {
            this.isLoading = true;
            if (!this.customer) return;

            await useOrderStore()
                .removeLineItems({
                    salesChannelId: this.customer.salesChannelId,
                    contextToken: this.cart.token,
                    lineItemKeys: lineItemKeys,
                })
                .then(() => {
                    // Remove promotion code tag if corresponding line item removed
                    lineItemKeys.forEach((key) => {
                        const removedTag = useOrderStore().promotionCodes.find(
                            (tag: PromotionCodeTag) => tag.discountId === key,
                        );

                        if (removedTag) {
                            useOrderStore().setPromotionCodes(
                                useOrderStore().promotionCodes.filter((item: PromotionCodeTag) => {
                                    return item.discountId !== removedTag.discountId;
                                }),
                            );
                        }
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        async loadCart(): Promise<void> {
            if (!this.customer) return;

            await useOrderStore().getCart({
                salesChannelId: this.customer.salesChannelId,
                contextToken: this.cart.token,
            });
        },

        sortByTaxRate(price: Array<CalculatedTax>): Array<CalculatedTax> {
            return price.sort((prev: CalculatedTax, current: CalculatedTax) => {
                return prev.taxRate - current.taxRate;
            });
        },

        onShippingChargeUpdated(amount: number): void {
            const positiveAmount = Math.abs(amount);
            this.cartDelivery.shippingCosts.unitPrice = positiveAmount;
            this.cartDelivery.shippingCosts.totalPrice = positiveAmount;
        },
    },
});
