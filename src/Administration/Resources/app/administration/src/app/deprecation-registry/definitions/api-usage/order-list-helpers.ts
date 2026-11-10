/**
 * @sw-package framework
 */

import { apiUsageMigration, componentMember, replaceComponentMethodCall, upgradeGuide } from '../helpers';

const COMPONENTS = ['sw-order-list'];

/** Each removed helper and what it returned for the order passed as `{0}`. */
const HELPERS = {
    getDelivery: '{0}.primaryOrderDelivery',
    transaction: '{0}.primaryOrderTransaction',
    getDeliveryAddress: '{0}.primaryOrderDelivery?.shippingOrderAddress',
    getDeliveryState: '{0}.primaryOrderDelivery?.stateMachineState',
    getTransactionState: '{0}.primaryOrderTransaction?.stateMachineState',
    getBillingAddress: '{0}.billingAddress',
};

export default apiUsageMigration({
    id: 'api.sw-order-list.delivery-helpers',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'sw-order-list reads the primary delivery and transaction of an order directly. Its criteria no longer load the addresses, deliveries and transactions associations.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-delivery-and-transaction-helpers-of-sw-order-list'),
    ],
    usage: [
        ...Object.entries(HELPERS).map(([name, replacement]) => {
            const example = replacement.replace('{0}', 'order');

            return replaceComponentMethodCall({
                components: COMPONENTS,
                name,
                with: replacement,
                message: `sw-order-list no longer has ${name}() in 6.8. Use ${example}.`,
                strictNullMessage: `sw-order-list no longer has ${name}() in 6.8. Use ${example}, which is undefined instead of null when it is missing.`,
            });
        }),
        componentMember({
            components: COMPONENTS,
            names: ['deliveryTooltip'],
            message:
                'sw-order-list no longer has deliveryTooltip() in 6.8. Build the tooltip from order.primaryOrderDelivery.shippingOrderAddress yourself.',
        }),
    ],
});
