/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-order-line-items-grid-sales-channel',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-order-line-items-grid-sales-channel that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks')],
    usage: [
        removeTemplateBlock({
            from: 'sw_order_line_items_grid_sales_channel_line_item_filter',
            moveTo: 'sw_order_line_items_grid_sales_channel_header',
            components: ['sw-order-line-items-grid-sales-channel'],
        }),
    ],
});
