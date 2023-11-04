import type { Entity } from '@shopware-ag/admin-extension-sdk/es/data/_internals/Entity';
import type { PropType } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';

import template from './sw-order-create-options.html.twig';
import './sw-order-create-options.scss';

import type {
    ContextSwitchParameters,
    Cart,
    CartDelivery,
} from '../../order.types';

/**
 * @package customer-order
 */

const { Component, State } = Shopware;
const { Criteria } = Shopware.Data;

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

        context: {
            type: Object as PropType<ContextSwitchParameters>,
            required: true,
        },
    },

    data(): {
        shippingCost: number,
        promotionCodeTags: string[],
        isSameAsBillingAddress: boolean,
        } {
        return {
            shippingCost: 0,
            isSameAsBillingAddress: false,
            promotionCodeTags: [],
        };
    },

    computed: {
        salesChannelId(): string {
            return State.get('swOrder').context?.salesChannel?.id ?? '';
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
            return State.get('swOrder').customer;
        },

        currency(): Entity<'currency'> {
            return State.get('swOrder').context.currency;
        },

        cart(): Cart {
            return State.get('swOrder').cart;
        },

        cartDelivery(): CartDelivery | null {
            return this.cart?.deliveries[0] as CartDelivery | null;
        },
    },

    watch: {
        cartDelivery: {
            immediate: true,
            handler(value): void {
                // eslint-disable-next-line max-len
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-assignment
                this.shippingCost = value?.shippingCosts?.totalPrice ?? 0;
            },
        },

        'context.currencyId': {
            async handler(): Promise<void> {
                // await this.getCurrency();
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
            async handler(): Promise<void> {
                await this.updateCartContext();
            },
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

        changePromotionCodes(value: string[]): void {
            this.$emit('promotions-change', value);
        },

        async updateCartContext(): Promise<void> {
            if (!this.salesChannelId) {
                return;
            }

            await this.updateOrderContext();
            await this.loadCart();
        },

        updateOrderContext(): Promise<void> {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-return
            return State.dispatch('swOrder/updateOrderContext', {
                context: this.context,
                salesChannelId: this.salesChannelId,
                contextToken: this.cart.token,
            });
        },

        loadCart(): Promise<void> {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-return
            return State.dispatch('swOrder/getCart', {
                salesChannelId: this.salesChannelId,
                contextToken: this.cart.token,
            });
        },

        onChangeShippingCost(value: number): void {
            this.$emit('shipping-cost-change', value);
        },
    },
});
