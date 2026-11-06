/**
 * @sw-package framework
 */

import { componentMembers, jsApiMigration, upgradeGuide } from '../helpers';

export default jsApiMigration({
    id: 'js-api.order-detail-shipping-costs',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'Shipping costs are edited in sw-order-detail-general: block sw_order_detail_general_line_items_summary_shipping_cost and onShippingChargeUpdated(amount).',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-shipping-cost-editing-of-sw-order-detail-details')],
    usage: [
        ...componentMembers({
            components: ['sw-order-detail-details'],
            members: ['shippingCosts', 'onShippingChargeEdited', 'currency'],
        }),
    ],
});
