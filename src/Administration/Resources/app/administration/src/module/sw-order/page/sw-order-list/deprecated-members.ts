/**
 * @sw-package checkout
 */

const { Criteria } = Shopware.Data;

type Order = EntitySchema.Entity<'order'>;
type OrderDelivery = EntitySchema.Entity<'order_delivery'>;
type OrderTransaction = EntitySchema.Entity<'order_transaction'>;

/**
 * The deprecated `computed` members of `sw-order-list`, apart from the component so the major removes them with
 * this file.
 *
 * @private
 * @example
 * computed: { ...deprecatedComputed, orderRepository() { … } }
 */
export const deprecatedComputed = {
    /**
     * @deprecated tag:v6.8.0 - will be removed without replacement
     */
    filterSelectCriteria() {
        Shopware.Feature.triggerDeprecationOrThrow(
            'V6_8_0_0',
            'sw-order-list.filterSelectCriteria is deprecated. Will be removed without replacement.',
        );

        const criteria = new Criteria(1, 1);

        criteria.addAggregation(Criteria.terms('affiliateCodes', 'affiliateCode', null, null, null));
        criteria.addAggregation(Criteria.terms('campaignCodes', 'campaignCode', null, null, null));
        criteria.addAggregation(Criteria.terms('promotionCodes', 'lineItems.payload.code', null, null, null));

        return criteria;
    },

    /**
     * @deprecated tag:v6.8.0 - Will be removed, because the filter is unused
     */
    dateFilter() {
        Shopware.Feature.triggerDeprecationOrThrow(
            'V6_8_0_0',
            'sw-order-list.dateFilter is deprecated and will be removed, because the filter is unused.',
        );

        return Shopware.Filter.getByName('date');
    },
};

/**
 * The deprecated `methods` of `sw-order-list`, apart from the component so the major removes them with this file.
 *
 * @private
 * @example
 * methods: { ...deprecatedMethods, onEdit(order) { … } }
 */
export const deprecatedMethods = {
    /**
     * @deprecated tag:v6.8.0 - will be removed, use order.primaryOrderDelivery instead
     */
    deliveryTooltip(deliveries: OrderDelivery[]): string {
        Shopware.Feature.triggerDeprecationOrThrow(
            'V6_8_0_0',
            'sw-order-list.deliveryTooltip() is deprecated. Use order.primaryOrderDelivery instead.',
        );

        return deliveries
            .map((delivery) => {
                return `${delivery.shippingOrderAddress?.street},
                    ${delivery.shippingOrderAddress?.zipcode}
                    ${delivery.shippingOrderAddress?.city}`;
            })
            .join('<hr style="margin: 8px 0">');
    },

    /**
     * @deprecated tag:v6.8.0 - will be removed, use order.billingAddress instead
     */
    getBillingAddress(order: Order) {
        Shopware.Feature.triggerDeprecationOrThrow(
            'V6_8_0_0',
            'sw-order-list.getBillingAddress() is deprecated. Use order.billingAddress instead.',
        );

        return order.addresses?.find((address) => {
            return address.id === order.billingAddressId;
        });
    },

    /**
     * @deprecated tag:v6.8.0 - Use listing mixin implementation directly
     */
    updateCriteria(criteria: unknown[]): unknown {
        Shopware.Feature.triggerDeprecationOrThrow(
            'V6_8_0_0',
            'sw-order-list.updateCriteria() is deprecated. Use listing mixin implementation directly.',
        );

        // Delegate to listing mixin implementation
        return Shopware.Mixin.getByName('listing').methods?.updateCriteria.call(this, criteria);
    },

    /**
     * @deprecated tag:v6.8.0 - will be removed, use order.primaryOrderTransaction instead
     */
    transaction(order: Order): OrderTransaction | null {
        Shopware.Feature.triggerDeprecationOrThrow(
            'V6_8_0_0',
            'sw-order-list.transaction() is deprecated. Use order.primaryOrderTransaction instead.',
        );

        if (!Shopware.Feature.isActive('v6.8.0.0')) {
            if (order.primaryOrderTransaction) {
                return order.primaryOrderTransaction;
            }

            const transactions = order.transactions ?? [];
            for (let i = 0; i < transactions.length; i += 1) {
                if (!['cancelled', 'failed'].includes(transactions[i].stateMachineState?.technicalName ?? '')) {
                    return transactions[i];
                }
            }

            return transactions[transactions.length - 1] ?? null;
        }

        return order.primaryOrderTransaction ?? null;
    },

    /**
     * @deprecated tag:v6.8.0 - will be removed, use order.primaryOrderDelivery instead
     */
    getDelivery(order: Order): OrderDelivery | null {
        Shopware.Feature.triggerDeprecationOrThrow(
            'V6_8_0_0',
            'sw-order-list.getDelivery() is deprecated. Use order.primaryOrderDelivery instead.',
        );

        if (!Shopware.Feature.isActive('v6.8.0.0')) {
            return order.primaryOrderDelivery ?? order.deliveries?.[0] ?? null;
        }

        return order.primaryOrderDelivery ?? null;
    },

    /**
     * @deprecated tag:v6.8.0 - will be removed, use order.primaryOrderDelivery.shippingOrderAddress instead
     */
    getDeliveryAddress(this: { getDelivery(order: Order): OrderDelivery | null }, order: Order) {
        Shopware.Feature.triggerDeprecationOrThrow(
            'V6_8_0_0',
            'sw-order-list.getDeliveryAddress() is deprecated. Use order.primaryOrderDelivery.shippingOrderAddress instead.',
        );

        return this.getDelivery(order)?.shippingOrderAddress ?? null;
    },

    /**
     * @deprecated tag:v6.8.0 - will be removed, use order.primaryOrderDelivery.stateMachineState instead
     */
    getDeliveryState(this: { getDelivery(order: Order): OrderDelivery | null }, order: Order) {
        Shopware.Feature.triggerDeprecationOrThrow(
            'V6_8_0_0',
            'sw-order-list.getDeliveryState() is deprecated. Use order.primaryOrderDelivery.stateMachineState instead.',
        );

        return this.getDelivery(order)?.stateMachineState ?? null;
    },

    /**
     * @deprecated tag:v6.8.0 - will be removed, use order.primaryOrderTransaction.stateMachineState instead
     */
    getTransactionState(this: { transaction(order: Order): OrderTransaction | null }, order: Order) {
        Shopware.Feature.triggerDeprecationOrThrow(
            'V6_8_0_0',
            'sw-order-list.getTransactionState() is deprecated. Use order.primaryOrderTransaction.stateMachineState instead.',
        );

        return this.transaction(order)?.stateMachineState ?? null;
    },
};
