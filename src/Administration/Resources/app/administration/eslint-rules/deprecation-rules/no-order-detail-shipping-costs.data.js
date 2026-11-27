/**
 * @sw-package framework
 *
 * The shipping cost editing of `sw-order-detail-details` that 6.8 removes, taken from the `@deprecated tag:v6.8.0`
 * markers. Shipping costs are edited in `sw-order-detail-general`. The blocks `sw_order_detail_details_shipping_costs`
 * and `sw_order_detail_details_order_promotion` are handled by `no-removed-twig-blocks`.
 */

module.exports = {
    id: 'order-shipping-costs',
    description: 'Marks the shipping cost members of sw-order-detail-details, which 6.8 moves to sw-order-detail-general',
    members: [
        { component: 'sw-order-detail-details', name: 'shippingCosts', message: 'sw-order-detail-details.shippingCosts is removed. Shipping costs are edited in sw-order-detail-general (data shippingCosts, onShippingChargeUpdated(amount) and onShippingChargeEdited()).' },
        { component: 'sw-order-detail-details', name: 'onShippingChargeEdited', message: 'Shipping costs are no longer edited in sw-order-detail-details. Override onShippingChargeEdited() in sw-order-detail-general instead; it takes no argument and reads this.shippingCosts.' },
        { component: 'sw-order-detail-details', name: 'currency', kinds: ['computed'], message: 'sw-order-detail-details.currency is removed without replacement. Read order.currency instead.' },
    ],
};
