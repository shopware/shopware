import type { Entity } from '@shopware-ag/admin-extension-sdk/es/data/_internals/Entity';
import template from './sw-order-create-details.html.twig';
// eslint-disable-next-line max-len
import type {
    Cart,
    LineItem,
    SalesChannelContext,
    PromotionCodeTag,
    ContextSwitchParameters, CartDelivery,
} from '../../order.types';
import type CriteriaType from '../../../../core/data/criteria.data';
import { LineItemType } from '../../order.types';
import type Repository from '../../../../core/data/repository.data';
import { get } from '../../../../core/service/utils/object.utils';

/**
 * @package customer-order
 */

const { Component, Mixin, State } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Component.wrapComponentConfig({
    template,

    inject: [
        'repositoryFactory',
        'cartStoreService',
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('cart-notification'),
    ],

    data(): {
        isLoading: boolean,
        showPromotionModal: boolean,
        promotionError: ShopwareHttpError|null,
        context: ContextSwitchParameters,
        } {
        return {
            showPromotionModal: false,
            promotionError: null,
            isLoading: false,
            context: {
                currencyId: '',
                paymentMethodId: '',
                shippingMethodId: '',
                languageId: '',
                billingAddressId: '',
                shippingAddressId: '',
            },
        };
    },

    computed: {
        salesChannelId(): string {
            return this.salesChannelContext?.salesChannel.id || '';
        },

        customer(): Entity<'customer'> | null {
            return State.get('swOrder').customer;
        },

        cart(): Cart {
            return State.get('swOrder').cart;
        },

        currency(): Entity<'currency'> {
            return State.get('swOrder').context.currency;
        },

        salesChannelContext(): SalesChannelContext {
            return State.get('swOrder').context;
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
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            return State.getters['swOrder/isCartTokenAvailable'] as boolean;
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
            return State.get('swOrder').disabledAutoPromotion;
        },

        promotionCodeTags: {
            get(): PromotionCodeTag[] {
                return State.get('swOrder').promotionCodes;
            },

            set(promotionCodeTags: PromotionCodeTag[]) {
                State.commit('swOrder/setPromotionCodes', promotionCodeTags);
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
                this.updateContext().finally(() => {
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
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent(): void {
            if (!this.customer) {
                this.$nextTick(() => {
                    void this.$router.push({ name: 'sw.order.create.initial' });
                });
            }

            this.context = {
                ...this.context,
                currencyId: this.salesChannelContext.context.currencyId,
                languageId: this.salesChannelContext.context.languageIdChain[0],
                shippingMethodId: this.salesChannelContext.shippingMethod.id,
                paymentMethodId: this.salesChannelContext.paymentMethod.id,
                // @ts-expect-error - activeBillingAddress is not defined in the type
                // eslint-disable-next-line max-len
                // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-member-access
                billingAddressId: this.salesChannelContext.customer?.activeBillingAddress?.id ?? '',
                // @ts-expect-error - activeShippingAddress is not defined in the type
                // eslint-disable-next-line max-len
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-assignment
                shippingAddressId: this.salesChannelContext.customer?.activeShippingAddress?.id ?? '',
            };
        },

        updateContext(): Promise<void> {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-return
            return State.dispatch('swOrder/updateOrderContext', {
                context: this.context,
                salesChannelId: this.customer?.salesChannelId,
                contextToken: this.cart.token,
            }).then(() => {
                return this.loadCart();
            });
        },

        loadCart() {
            return State.dispatch('swOrder/getCart', {
                salesChannelId: this.customer?.salesChannelId,
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

        updatePromotionList() {
            // Update data and isInvalid flag for each item in promotionCodeTags
            this.promotionCodeTags = this.promotionCodeTags.map((tag: PromotionCodeTag): PromotionCodeTag => {
                const matchedItem = this.promotionCodeLineItems
                    .find((lineItem: LineItem): boolean => lineItem.payload?.code === tag.code);

                if (matchedItem) {
                    return { ...matchedItem.payload, isInvalid: false } as PromotionCodeTag;
                }

                return { ...tag, isInvalid: true } as PromotionCodeTag;
            });

            // Add new items from promotionCodeLineItems which promotionCodeTags doesn't contain
            this.promotionCodeLineItems.forEach((lineItem: LineItem): void => {
                const matchedItem = this.promotionCodeTags
                    .find((tag: PromotionCodeTag): boolean => tag.code === lineItem.payload?.code);

                if (!matchedItem) {
                    this.promotionCodeTags = [
                        ...this.promotionCodeTags,
                        { ...lineItem.payload, isInvalid: false } as PromotionCodeTag,
                    ];
                }
            });
        },

        toggleAutomaticPromotions(visibility: boolean): void {
            this.showPromotionModal = visibility;
            if (visibility) {
                State.commit('swOrder/setDisabledAutoPromotion', true);
                return;
            }

            this.isLoading = true;
            void this.cartStoreService.enableAutomaticPromotions(
                this.cart.token,
                { salesChannelId: this.salesChannelId },
            ).then(() => {
                State.commit('swOrder/setDisabledAutoPromotion', false);

                return this.loadCart();
            }).finally(() => {
                this.isLoading = false;
            });
        },

        onClosePromotionModal() {
            this.showPromotionModal = false;
            State.commit('swOrder/setDisabledAutoPromotion', false);
        },

        onSavePromotionModal() {
            this.showPromotionModal = false;
            State.commit('swOrder/setDisabledAutoPromotion', true);

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

            State.dispatch('swOrder/modifyShippingCosts', {
                salesChannelId: this.salesChannelId,
                contextToken: this.cart.token,
                shippingCosts: this.cartDelivery.shippingCosts,
            }).catch((error) => {
                this.$emit('error', error);
            }).finally(() => {
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
                    detail: this.$tc('sw-order.createBase.textInvalidPromotionCode'),
                } as ShopwareHttpError;
            }
        },

        onSubmitCode(code: string): Promise<void> {
            this.isLoading = true;

            // eslint-disable-next-line @typescript-eslint/no-unsafe-return
            return State.dispatch('swOrder/addPromotionCode', {
                salesChannelId: this.customer?.salesChannelId,
                contextToken: this.cart.token,
                code,
            }).finally(() => {
                this.isLoading = false;
            });
        },
    },
});
