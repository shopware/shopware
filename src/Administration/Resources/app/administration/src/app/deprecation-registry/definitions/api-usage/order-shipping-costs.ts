/**
 * @sw-package framework
 */

import { apiUsageMigration, componentMember, upgradeGuide } from '../helpers';

export default apiUsageMigration({
    id: 'api.sw-order-detail-details.shipping-costs',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Shipping costs are edited in the general view of the order detail page.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-shipping-cost-editing-of-sw-order-detail-details'),
    ],
    usage: [
        componentMember({
            components: ['sw-order-detail-details'],
            names: [
                'shippingCosts',
                'onShippingChargeEdited',
                'currency',
            ],
            message:
                'Shipping costs are edited in sw-order-detail-general in 6.8: block sw_order_detail_general_line_items_summary_shipping_cost and onShippingChargeUpdated(amount).',
        }),
    ],
});
