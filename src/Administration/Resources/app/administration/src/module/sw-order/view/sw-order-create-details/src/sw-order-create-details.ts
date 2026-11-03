import template from './sw-order-create-details.html.twig';
import {
    type Cart,
    type CartDelivery,
    cartNotificationMixin,
    cartStoreService,
    type ContextSwitchParameters,
    Criteria,
    type CriteriaType,
    get,
    type LineItem,
    LineItemType,
    notificationMixin,
    type PromotionCodeTag,
    type Repository,
    repositoryFactoryService,
    type SalesChannelContext,
    useContextStore,
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

    inject: [repositoryFactoryService, cartStoreService],

    emits: ['error'],

    mixins: [notificationMixin, cartNotificationMixin],

    data(): {
        isLoading: boolean;
        showPromotionModal: boolean;
        promotionError: ShopwareHttpError | null;
        context: ContextSwitchParameters;
    } {
        return {
            showPromotionModal: false,
            promotionError: null,
            isLoading: false,
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
            return this.salesChannelContext?.salesChannel.id || ('' as EntityKey<'sales_channel'>);
        },

        customer(): Entity<'customer'> | null {
            return useOrderStore().customer;
        },

        cart(): Cart {
            return useOrderStore().cart;
        },

        currency(): Entity<'currency'> {
            return useOrderStore().context.currency;
        },

        salesChannelContext(): SalesChannelContext {
            return useOrderStore().context;
        },

        email(): string {
            return this.customer?.email || '';
        },

        phoneNumber(): string {
            return this.customer?.defaultBillingAddress?.phoneNumber || '';
        },

        cartDelivery(): CartDelivery | null {
            return get(this.cart, 'deliveries[0]', null);
        },

        shippingCosts: {
            get(): number {
                return this.cartDelivery?.shippingCosts.totalPrice || 0.0;
            },
            set(value: number): void {
                this.modifyShippingCosts(value);
            },
        },

        deliveryDate(): string {
            return this.cartDelivery?.deliveryDate.earliest || '';
        },

        shippingMethodCriteria(): CriteriaType {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('salesChannels.id', this.salesChannelId));

            return criteria;
        },

        paymentMethodCriteria(): CriteriaType {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('salesChannels.id', this.salesChannelId));

            criteria.addFilter(Criteria.equals('active', 1));

            return criteria;
        },

        languageCriteria(): CriteriaType {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('salesChannels.id', this.salesChannelId));

            return criteria;
        },

        currencyCriteria(): CriteriaType {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('salesChannels.id', this.salesChannelId));

            return criteria;
        },

        currencyRepository(): Repository<'currency'> {
            return this.repositoryFactory.create('currency');
        },

        isCartTokenAvailable(): boolean {
            return useOrderStore().isCartTokenAvailable;
        },

        hasLineItem(): boolean {
            return this.cart?.lineItems.filter((item: LineItem) => item.hasOwnProperty('id')).length > 0;
        },

        promotionCodeLineItems(): LineItem[] {
            return this.cart?.lineItems.filter((item: LineItem) => {
                return item.type === LineItemType.PROMOTION && item?.payload?.code;
            });
        },

        disabledAutoPromotion(): boolean {
            return useOrderStore().disabledAutoPromotion;
        },

        promotionCodeTags: {
            get(): PromotionCodeTag[] {
                return useOrderStore().promotionCodes;
            },

            set(promotionCodeTags: PromotionCodeTag[]) {
                useOrderStore().setPromotionCodes(promotionCodeTags);
            },
        },
    },

    watch: {
        context: {
            deep: true,
            handler(): void {
                if (!this.customer || !this.isCartTokenAvailable) {
                    return;
                }

                this.isLoading = true;
                void this.updateContext().finally(() => {
                    this.isLoading = false;
                });
            },
        },

        cart: {
            deep: true,
            immediate: true,
            handler: 'updatePromotionList',
        },

        promotionCodeTags: {
            handler: 'handlePromotionCodeTags',
        },

        'context.languageId'(languageId: EntityKey<'language'>) {
            if (!languageId) {
                return;
            }

            useContextStore().api.languageId = languageId;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent(): void {
            if (!this.customer) {
                void this.$nextTick(() => {
                    void this.$router.push({ name: 'sw.order.create.initial' });
                });
            }

            this.context = {
                ...this.context,
                currencyId: this.salesChannelContext.context.currencyId,
                languageId: this.salesChannelContext.context.languageIdChain[0],
                shippingMethodId: this.salesChannelContext.shippingMethod.id,
                paymentMethodId: this.salesChannelContext.paymentMethod.id,
                billingAddressId:
                    this.salesChannelContext.customer?.activeBillingAddress?.id ?? ('' as EntityKey<'customer_address'>),
                shippingAddressId:
                    this.salesChannelContext.customer?.activeShippingAddress?.id ?? ('' as EntityKey<'customer_address'>),
            };
        },

        async updateContext(): Promise<void> {
            if (!this.customer) return;
            await useOrderStore()
                .updateOrderContext({
                    context: this.context,
                    salesChannelId: this.customer.salesChannelId,
                    contextToken: this.cart.token,
                })
                .then(() => {
                    return this.loadCart();
                });
        },

        async loadCart() {
            if (!this.customer) return;

            await useOrderStore().getCart({
                salesChannelId: this.customer.salesChannelId,
                contextToken: this.cart.token,
            });
        },

        onRemoveExistingCode(item: PromotionCodeTag) {
            if (item.isInvalid) {
                this.promotionCodeTags = this.promotionCodeTags.filter((tag: PromotionCodeTag) => tag.code !== item.code);

                return Promise.resolve();
            }

            return this.onRemoveItems([item.discountId]);
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
                        const removedTag = this.promotionCodeTags.find((tag: PromotionCodeTag) => tag.discountId === key);

                        if (removedTag) {
                            this.promotionCodeTags = this.promotionCodeTags.filter((item: PromotionCodeTag) => {
                                return item.discountId !== removedTag.discountId;
                            });
                        }
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        updatePromotionList() {
            // Synchronize the tags with the applied promotion line items and discard rejected codes
            this.promotionCodeTags = this.promotionCodeTags.flatMap((tag: PromotionCodeTag): PromotionCodeTag[] => {
                const matchedItem = this.promotionCodeLineItems.find(
                    (lineItem: LineItem): boolean => lineItem.payload?.code === tag.code,
                );

                if (matchedItem) {
                    return [
                        {
                            ...matchedItem.payload,
                            isInvalid: false,
                        } as PromotionCodeTag,
                    ];
                }

                return [];
            });

            // Add new items from promotionCodeLineItems which promotionCodeTags doesn't contain
            this.promotionCodeLineItems.forEach((lineItem: LineItem): void => {
                const matchedItem = this.promotionCodeTags.find(
                    (tag: PromotionCodeTag): boolean => tag.code === lineItem.payload?.code,
                );

                if (!matchedItem) {
                    this.promotionCodeTags = [
                        ...this.promotionCodeTags,
                        {
                            ...lineItem.payload,
                            isInvalid: false,
                        } as PromotionCodeTag,
                    ];
                }
            });
        },

        toggleAutomaticPromotions(visibility: boolean): void {
            this.showPromotionModal = visibility;
            if (visibility) {
                useOrderStore().setDisabledAutoPromotion(true);
                return;
            }

            this.isLoading = true;
            void this.cartStoreService
                .enableAutomaticPromotions(this.cart.token, {
                    salesChannelId: this.salesChannelId,
                })
                .then(() => {
                    useOrderStore().setDisabledAutoPromotion(false);

                    return this.loadCart();
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onClosePromotionModal() {
            this.showPromotionModal = false;
            useOrderStore().setDisabledAutoPromotion(false);
        },

        onSavePromotionModal() {
            this.showPromotionModal = false;
            useOrderStore().setDisabledAutoPromotion(true);

            return this.loadCart().finally(() => {
                this.isLoading = false;
            });
        },

        modifyShippingCosts(amount: number) {
            const positiveAmount = Math.abs(amount);
            if (!this.cartDelivery) {
                return;
            }
            this.cartDelivery.shippingCosts.unitPrice = positiveAmount;
            this.cartDelivery.shippingCosts.totalPrice = positiveAmount;
            this.isLoading = true;

            useOrderStore()
                .modifyShippingCosts({
                    salesChannelId: this.salesChannelId,
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

        handlePromotionCodeTags(newValue: PromotionCodeTag[], oldValue: PromotionCodeTag[]) {
            this.promotionError = null;

            if (newValue.length < oldValue.length) {
                return;
            }

            const promotionCodeLength = this.promotionCodeTags.length;
            const latestTag = this.promotionCodeTags[promotionCodeLength - 1];

            if (newValue.length > oldValue.length) {
                void this.onSubmitCode(latestTag.code);
            }

            if (promotionCodeLength > 0 && latestTag.isInvalid) {
                this.promotionError = {
                    detail: this.$t('sw-order.createBase.textInvalidPromotionCode'),
                } as ShopwareHttpError;
            }
        },

        async onSubmitCode(code: string): Promise<void> {
            this.isLoading = true;
            if (!this.customer) return;

            await useOrderStore()
                .addPromotionCode({
                    salesChannelId: this.customer?.salesChannelId,
                    contextToken: this.cart.token,
                    code,
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },
    },
});
