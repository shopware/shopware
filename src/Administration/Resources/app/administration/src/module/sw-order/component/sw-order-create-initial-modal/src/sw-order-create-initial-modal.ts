import template from './sw-order-create-initial-modal.html.twig';
import './sw-order-create-initial-modal.scss';
import {
    type Cart,
    type CartDelivery,
    cartNotificationMixin,
    type ContextSwitchParameters,
    featureService,
    getCartStoreService,
    type LineItem,
    LineItemType,
    notificationMixin,
    type SalesChannelContext,
    type TabItem,
    useOrderStore,
    wrapComponentConfig,
} from '../dependencies';

interface PromotionCodeItem {
    type: string;
    referencedId: string;
}

/**
 * @sw-package checkout
 *
 * @public
 */
export default wrapComponentConfig({
    template,

    inject: [featureService],

    emits: ['modal-close', 'order-preview'],

    mixins: [notificationMixin, cartNotificationMixin],

    data(): {
        isLoading: boolean;
        isProductGridLoading: boolean;
        disabledAutoPromotion: boolean;
        sendOrderConfirmationMail: boolean;
        promotionCodes: string[];
        productItems: LineItem[];
        context: ContextSwitchParameters;
        shippingCosts: number | null;
        activeTab: string;
    } {
        return {
            productItems: [],
            promotionCodes: [],
            isLoading: false,
            isProductGridLoading: false,
            disabledAutoPromotion: false,
            sendOrderConfirmationMail: true,
            shippingCosts: null,
            activeTab: 'customer',
            context: {
                currencyId: '' as EntityKey<'currency'>,
                paymentMethodId: '' as EntityKey<'payment_method'>,
                shippingMethodId: '' as EntityKey<'shipping_method'>,
                languageId: '' as EntityKey<'language'>,
                billingAddressId: '' as EntityKey<'customer_address'>,
                shippingAddressId: '' as EntityKey<'customer_address'>,
            },
        };
    },

    computed: {
        salesChannelId(): EntityKey<'sales_channel'> {
            return this.customer?.salesChannelId ?? ('' as EntityKey<'sales_channel'>);
        },

        salesChannelContext(): SalesChannelContext {
            return useOrderStore().context;
        },

        currency(): Entity<'currency'> {
            return this.salesChannelContext.currency;
        },

        cart(): Cart {
            return useOrderStore().cart;
        },

        customer(): Entity<'customer'> | null {
            return useOrderStore().customer;
        },

        isCustomerActive(): boolean {
            return useOrderStore().isCustomerActive;
        },

        promotionCodeItems(): PromotionCodeItem[] {
            return this.promotionCodes.map((code) => {
                return {
                    type: LineItemType.PROMOTION,
                    referencedId: code,
                };
            });
        },

        cartDelivery(): CartDelivery | null {
            return this.cart?.deliveries[0] as CartDelivery | null;
        },

        orderCreateInitialModalTabs(): TabItem[] {
            return [
                {
                    label: this.$t('sw-order.initialModal.tabCustomer'),
                    name: 'customer',
                },
                {
                    label: this.$t('sw-order.initialModal.tabProducts'),
                    name: 'products',
                    disabled: !this.customer || undefined,
                },
                {
                    label: this.$t('sw-order.initialModal.tabOptions'),
                    name: 'options',
                    disabled: !this.customer || undefined,
                },
            ];
        },
    },

    watch: {
        salesChannelContext(value: SalesChannelContext): void {
            // Update context after switching customer successfully
            this.context = {
                ...this.context,
                currencyId: value.context.currencyId,
                languageId: value.context.languageIdChain[0],
                shippingMethodId: value.shippingMethod.id,
                paymentMethodId: value.paymentMethod.id,
                billingAddressId: value.customer?.activeBillingAddress?.id ?? ('' as EntityKey<'customer_address'>),
                shippingAddressId: value.customer?.activeShippingAddress?.id ?? ('' as EntityKey<'customer_address'>),
            };
        },
    },

    methods: {
        async onCloseModal(): Promise<void> {
            if (!this.customer || !this.cart.token) {
                this.$emit('modal-close');
                return;
            }

            try {
                await this.cancelCart();
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-order.initialModal.messageCancelCartError'),
                });
            }

            this.$emit('modal-close');
        },

        async onPreviewOrder(): Promise<void> {
            const promises = [];

            this.isLoading = true;
            useOrderStore().setSendOrderConfirmationMail(this.sendOrderConfirmationMail);

            promises.push(this.updateOrderContext());

            if (this.disabledAutoPromotion) {
                promises.push(this.disableAutoAppliedPromotions());
            }

            if (this.promotionCodes.length) {
                promises.push(this.addPromotionCodes());
            }

            if (this.shippingCosts !== null && this.shippingCosts !== this.cartDelivery?.shippingCosts?.totalPrice) {
                promises.push(this.modifyShippingCost(this.shippingCosts));
            }

            try {
                const responses = await Promise.all(promises);
                if (responses) {
                    this.$emit('order-preview');
                }
            } finally {
                this.isLoading = false;
            }
        },

        async onSaveItem(item: LineItem): Promise<void> {
            this.isProductGridLoading = true;

            try {
                await useOrderStore().saveLineItem({
                    salesChannelId: this.salesChannelId,
                    contextToken: this.cart.token,
                    item,
                });
            } finally {
                this.isProductGridLoading = false;
            }
        },

        async addPromotionCodes(): Promise<void> {
            if (!this.customer) return;

            await useOrderStore().saveMultipleLineItems({
                salesChannelId: this.customer?.salesChannelId,
                contextToken: this.cart.token,
                items: this.promotionCodeItems as unknown as LineItem[],
            });
        },

        updatePromotion(promotions: string[]): void {
            this.promotionCodes = promotions;
        },

        async onRemoveItems(lineItemKeys: string[]): Promise<void> {
            this.isProductGridLoading = true;

            try {
                await useOrderStore().removeLineItems({
                    salesChannelId: this.salesChannelId,
                    contextToken: this.cart.token,
                    lineItemKeys: lineItemKeys,
                });
            } finally {
                this.isProductGridLoading = false;
            }
        },

        updateAutoPromotionToggle(value: boolean): void {
            this.disabledAutoPromotion = value;
        },

        updateSendOrderConfirmationMail(value: boolean): void {
            this.sendOrderConfirmationMail = value;
        },

        updateShippingCost(value: number): void {
            this.shippingCosts = value;
        },

        async updateOrderContext(): Promise<void> {
            await useOrderStore().updateOrderContext({
                context: this.context,
                salesChannelId: this.salesChannelId,
                contextToken: this.cart.token,
            });
        },

        disableAutoAppliedPromotions(): Promise<void> {
            const additionalParams = { salesChannelId: this.salesChannelId };

            return getCartStoreService()
                .disableAutomaticPromotions(this.cart.token, additionalParams)
                .then(() => {
                    useOrderStore().setDisabledAutoPromotion(true);
                });
        },

        async modifyShippingCost(amount: number): Promise<void> {
            if (!this.cartDelivery) {
                return;
            }

            const positiveAmount = Math.abs(amount);
            this.cartDelivery.shippingCosts.unitPrice = positiveAmount;
            this.cartDelivery.shippingCosts.totalPrice = positiveAmount;

            if (!this.customer) return;

            await useOrderStore().modifyShippingCosts({
                salesChannelId: this.customer?.salesChannelId,
                contextToken: this.cart.token,
                shippingCosts: this.cartDelivery?.shippingCosts,
            });
        },

        cancelCart(): Promise<void> {
            return useOrderStore().cancelCart({
                salesChannelId: this.salesChannelId,
                contextToken: this.cart.token,
            });
        },
    },
});
