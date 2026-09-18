import notificationMixin from 'shopware:mixins/notification';
import cartNotificationMixin from 'shopware:mixins/cart-notification';
import useSwOrderStore from 'shopware:stores/swOrder';
import template from './sw-order-create-details.html.twig';
import type {
    Cart,
    LineItem,
    SalesChannelContext,
    PromotionCodeTag,
    ContextSwitchParameters,
    CartDelivery,
} from '../../order.types';
import type CriteriaType from '../../../../core/data/criteria.data';
import { LineItemType } from '../../order.types';
import type Repository from '../../../../core/data/repository.data';
import { get } from '../../../../core/service/utils/object.utils';
import { Criteria } from 'shopware:data';
import useContextStore from 'shopware:stores/context';

/**
 * @sw-package checkout
 */

const { Component } = Shopware;
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Component.wrapComponentConfig({
    template,

    inject: [
        'repositoryFactory',
        'cartStoreService',
    ],

    mixins: [
        notificationMixin,
        cartNotificationMixin,
    ],

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
            return useSwOrderStore().customer;
        },

        cart(): Cart {
            return useSwOrderStore().cart;
        },

        currency(): Entity<'currency'> {
            return useSwOrderStore().context.currency;
        },

        salesChannelContext(): SalesChannelContext {
            return useSwOrderStore().context;
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
            return useSwOrderStore().isCartTokenAvailable;
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
            return useSwOrderStore().disabledAutoPromotion;
        },

        promotionCodeTags: {
            get(): PromotionCodeTag[] {
                return useSwOrderStore().promotionCodes;
            },

            set(promotionCodeTags: PromotionCodeTag[]) {
                useSwOrderStore().setPromotionCodes(promotionCodeTags);
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
            await useSwOrderStore()
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

            await useSwOrderStore().getCart({
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

            await useSwOrderStore()
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
                useSwOrderStore().setDisabledAutoPromotion(true);
                return;
            }

            this.isLoading = true;
            void this.cartStoreService
                .enableAutomaticPromotions(this.cart.token, {
                    salesChannelId: this.salesChannelId,
                })
                .then(() => {
                    useSwOrderStore().setDisabledAutoPromotion(false);

                    return this.loadCart();
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onClosePromotionModal() {
            this.showPromotionModal = false;
            useSwOrderStore().setDisabledAutoPromotion(false);
        },

        onSavePromotionModal() {
            this.showPromotionModal = false;
            useSwOrderStore().setDisabledAutoPromotion(true);

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

            useSwOrderStore()
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

            await useSwOrderStore()
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
