/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-condition-line-item-purchase-price',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-condition-line-item-purchase-price that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks')],
    usage: [
        removeTemplateBlock({
            from: 'sw_condition_line_item_purchase_price_field_gross_net',
            moveTo: 'sw-condition-generic',
            components: ['sw-condition-line-item-purchase-price'],
        }),
        removeTemplateBlock({
            from: 'sw_condition_line_item_purchase_price_field_operator',
            moveTo: 'sw-condition-generic',
            components: ['sw-condition-line-item-purchase-price'],
        }),
        removeTemplateBlock({
            from: 'sw_condition_line_item_purchase_price_field_amount',
            moveTo: 'sw-condition-generic',
            components: ['sw-condition-line-item-purchase-price'],
        }),
    ],
});
