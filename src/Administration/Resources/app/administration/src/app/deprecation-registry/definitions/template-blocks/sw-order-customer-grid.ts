/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-order-customer-grid',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-order-customer-grid that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks')],
    usage: [
        removeTemplateBlock({
            from: 'sw_order_customer_grid_empty_state_icon',
            moveTo: 'sw_order_customer_grid_empty_state (pass the icon and texts as props of mt-empty-state)',
            components: ['sw-order-customer-grid'],
        }),
    ],
});
