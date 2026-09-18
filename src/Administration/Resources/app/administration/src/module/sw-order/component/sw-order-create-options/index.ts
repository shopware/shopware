import useSwOrderStore from 'shopware:stores/swOrder';
import useContextStore from 'shopware:stores/context';
import type CriteriaType from 'src/core/data/criteria.data';

import template from './sw-order-create-options.html.twig';
import './sw-order-create-options.scss';

import type { ContextSwitchParameters, Cart, CartDelivery } from '../../order.types';
import { Criteria } from 'shopware:data';

/**
 * @sw-package checkout
 */

const { Component } = Shopware;
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Component.wrapComponentConfig({
    template,

    props: {
        promotionCodes: {
            type: Array as PropType<string[]>,
            required: true,
        },

        disabledAutoPromotion: {
            type: Boolean,
            required: true,
        },

        sendOrderConfirmationMail: {
            type: Boolean,
            required: false,
            default: true,
        },

        context: {
            type: Object as PropType<ContextSwitchParameters>,
            required: true,
        },
    },

    data(): {
        shippingCost: number;
        promotionCodeTags: string[];
        isSameAsBillingAddress: boolean;
    } {
        return {
            shippingCost: 0,
            isSameAsBillingAddress: false,
            promotionCodeTags: [],
        };
    },

    computed: {
        salesChannelId(): EntityKey<'sales_channel'> {
            return this.customer?.salesChannelId ?? useSwOrderStore().context?.salesChannel?.id ?? '';
        },

        salesChannelCriteria(): CriteriaType {
            const criteria = new Criteria();

            if (this.salesChannelId) {
                criteria.addFilter(Criteria.equals('salesChannels.id', this.salesChannelId));
            }

            return criteria;
        },

        shippingMethodCriteria(): CriteriaType {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('active', 1));

            if (this.salesChannelId) {
                criteria.addFilter(Criteria.equals('salesChannels.id', this.salesChannelId));
            }

            return criteria;
        },

        paymentMethodCriteria(): CriteriaType {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('active', 1));

            if (this.salesChannelId) {
                criteria.addFilter(Criteria.equals('salesChannels.id', this.salesChannelId));
            }

            return criteria;
        },

        customer(): Entity<'customer'> | null {
            return useSwOrderStore().customer;
        },

        currency(): Entity<'currency'> {
            return useSwOrderStore().context.currency;
        },

        cart(): Cart {
            return useSwOrderStore().cart;
        },

        cartDelivery(): CartDelivery | null {
            return this.cart?.deliveries[0] as CartDelivery | null;
        },
    },

    watch: {
        cartDelivery: {
            immediate: true,
            handler(value): void {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-assignment
                this.shippingCost = value?.shippingCosts?.totalPrice ?? 0;
            },
        },

        'context.currencyId': {
            async handler(currencyId: EntityKey<'currency'>): Promise<void> {
                if (!currencyId || currencyId === useSwOrderStore().context?.context?.currencyId) {
                    return;
                }

                await this.updateCartContext();
            },
        },

        'context.shippingAddressId': {
            handler(): void {
                this.updateSameAsBillingAddressToggle();
            },
        },

        'context.billingAddressId': {
            handler(): void {
                this.updateSameAsBillingAddressToggle();
            },
        },

        'context.shippingMethodId': {
            async handler(shippingMethodId: EntityKey<'shipping_method'>): Promise<void> {
                if (!shippingMethodId || shippingMethodId === useSwOrderStore().context?.shippingMethod?.id) {
                    return;
                }

                await this.updateCartContext();
            },
        },

        'context.languageId'(languageId: EntityKey<'language'>) {
            if (!languageId) {
                return;
            }

            useContextStore().api.languageId = languageId;
        },

        isSameAsBillingAddress(value): void {
            if (!value) {
                return;
            }

            this.context.shippingAddressId = this.context.billingAddressId;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        updateSameAsBillingAddressToggle(): void {
            this.isSameAsBillingAddress = this.context.shippingAddressId === this.context.billingAddressId;
        },

        createdComponent(): void {
            this.promotionCodeTags = [...this.promotionCodes];
            this.isSameAsBillingAddress = this.context.shippingAddressId === this.context.billingAddressId;
        },

        validatePromotions(searchTerm: string): boolean {
            const promotionCode = searchTerm.trim();

            if (promotionCode.length <= 0) {
                return false;
            }

            const isExist = this.promotionCodes.find((code: string) => code === promotionCode);
            return !isExist;
        },

        onToggleAutoPromotion(value: boolean): void {
            this.$emit('auto-promotion-toggle', value);
        },

        onToggleSendOrderConfirmationMail(value: boolean): void {
            this.$emit('send-order-confirmation-mail-toggle', value);
        },

        changePromotionCodes(value: string[]): void {
            this.$emit('promotions-change', value);
        },

        async updateCartContext(): Promise<void> {
            if (!this.salesChannelId || !this.customer || !this.cart.token) {
                return;
            }

            await this.updateOrderContext();
            await this.loadCart();
        },

        async updateOrderContext(): Promise<void> {
            await useSwOrderStore().updateOrderContext({
                context: this.context,
                salesChannelId: this.salesChannelId,
                contextToken: this.cart.token,
            });
        },

        async loadCart(): Promise<void> {
            await useSwOrderStore().getCart({
                salesChannelId: this.salesChannelId,
                contextToken: this.cart.token,
            });
        },

        onChangeShippingCost(value: number): void {
            this.$emit('shipping-cost-change', value);
        },
    },
});
