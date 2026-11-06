/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-order-detail-details',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-order-detail-details that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks')],
    usage: [
        removeTemplateBlock({ from: 'sw_order_detail_details_order_promotion', components: ['sw-order-detail-details'] }),
        removeTemplateBlock({
            from: 'sw_order_detail_details_shipping_costs',
            moveTo: 'sw_order_detail_general_line_items_summary_shipping_cost of sw-order-detail-general',
            components: ['sw-order-detail-details'],
        }),
    ],
});
