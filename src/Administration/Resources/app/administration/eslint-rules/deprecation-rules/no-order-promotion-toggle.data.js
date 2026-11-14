/**
 * @sw-package framework
 *
 * The automatic promotion toggle of the order detail page that 6.8 removes in favor of
 * `applyAutomaticPromotions()`: the members of `sw-order-promotion-field`, the service method and the Admin API
 * route, taken from the `@deprecated tag:v6.8.0` markers. `sw-order-create-details.toggleAutomaticPromotions()`
 * belongs to creating an order and stays.
 */

const NO_REPLACEMENT = 'is removed without replacement. The field applies automatic promotions with applyAutomaticPromotions() now.';

module.exports = {
    id: 'order-promotion-toggle',
    description: 'Marks the automatic promotion toggle of the order detail page, which 6.8 replaces with applyAutomaticPromotions()',
    members: [
        { component: 'sw-order-promotion-field', name: 'automaticPromotions', message: `sw-order-promotion-field.automaticPromotions ${NO_REPLACEMENT}` },
        { component: 'sw-order-promotion-field', name: 'hasAutomaticPromotions', message: `sw-order-promotion-field.hasAutomaticPromotions ${NO_REPLACEMENT}` },
        { component: 'sw-order-promotion-field', name: 'changesetGenerator', message: `sw-order-promotion-field.changesetGenerator ${NO_REPLACEMENT}` },
        { component: 'sw-order-promotion-field', name: 'hasOrderUnsavedChanges', message: `sw-order-promotion-field.hasOrderUnsavedChanges ${NO_REPLACEMENT}` },
        { component: 'sw-order-promotion-field', name: 'disabledAutoPromotions', message: `sw-order-promotion-field.disabledAutoPromotions ${NO_REPLACEMENT}` },
        { component: 'sw-order-promotion-field', name: 'createdComponent', kinds: ['methods'], message: `sw-order-promotion-field.createdComponent() ${NO_REPLACEMENT}` },
        { component: 'sw-order-promotion-field', name: 'emitLoadingChange', message: "sw-order-promotion-field.emitLoadingChange() is removed. Use Shopware.Store.get('swOrderDetail').setLoading(['recalculation', state])." },
        { component: 'sw-order-promotion-field', name: 'handleUnsavedOrderChangesResponse', message: `sw-order-promotion-field.handleUnsavedOrderChangesResponse() ${NO_REPLACEMENT}` },
        { component: 'sw-order-promotion-field', name: 'deleteAutomaticPromotions', message: 'sw-order-promotion-field.deleteAutomaticPromotions() is removed. Use applyAutomaticPromotions().' },
        { component: 'sw-order-promotion-field', name: 'toggleAutomaticPromotions', message: 'sw-order-promotion-field.toggleAutomaticPromotions(state) is removed. Use applyAutomaticPromotions(); it takes no argument.' },
        { component: 'sw-order-promotion-field', name: 'getLineItemByPromotionCode', message: `sw-order-promotion-field.getLineItemByPromotionCode() ${NO_REPLACEMENT}` },
    ],
    calls: [
        { service: 'orderService', method: 'toggleAutomaticPromotions', message: 'orderService.toggleAutomaticPromotions() is removed. Use orderService.applyAutomaticPromotions(orderId, versionId); it responds with { errors } instead of a cart.' },
    ],
    literals: [
        { value: /\/toggleAutomaticPromotions\b/, message: 'The Admin API route /api/_action/order/{orderId}/toggleAutomaticPromotions is removed. Use /api/_action/order/{orderId}/applyAutomaticPromotions; it responds with { errors } instead of a cart.' },
    ],
};
