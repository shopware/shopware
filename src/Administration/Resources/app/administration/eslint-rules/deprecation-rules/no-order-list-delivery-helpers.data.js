/**
 * @sw-package framework
 *
 * The members of `sw-order-list` that 6.8 removes, from the `@deprecated tag:v6.8.0` markers, and the order
 * associations the list no longer loads with the `v6.8.0.0` flag.
 */

const COMPONENT = 'sw-order-list';

module.exports = {
    component: COMPONENT,

    // Accessor → the path on the order that replaces it, the body of the method in 6.8
    accessors: {
        transaction: 'primaryOrderTransaction',
        getDelivery: 'primaryOrderDelivery',
        getDeliveryAddress: 'primaryOrderDelivery?.shippingOrderAddress',
        getDeliveryState: 'primaryOrderDelivery?.stateMachineState',
        getTransactionState: 'primaryOrderTransaction?.stateMachineState',
        getBillingAddress: 'billingAddress',
    },

    // Members without a replacement on the order
    removed: {
        deliveryTooltip: `deliveryTooltip() of ${COMPONENT} is removed in 6.8. The list only loads the primary delivery; `
            + 'format order.primaryOrderDelivery?.shippingOrderAddress yourself.',
        filterLoading: `filterLoading of ${COMPONENT} is removed in 6.8 without replacement.`,
        filterSelectCriteria: `filterSelectCriteria of ${COMPONENT} is removed in 6.8 without replacement.`,
    },

    // Members that still resolve through a mixin, so only `this.$super('<name>')` breaks
    superOnly: {
        updateCriteria: `updateCriteria() of ${COMPONENT} is removed in 6.8, so this.$super('updateCriteria') has nothing to call. `
            + "Call Shopware.Mixin.getByName('listing').methods.updateCriteria.call(this, criteria) instead.",
    },

    associations: {
        deliveries: 'order.primaryOrderDelivery',
        transactions: 'order.primaryOrderTransaction',
        addresses: 'order.billingAddress or order.primaryOrderDelivery?.shippingOrderAddress',
    },
};
