/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.rule-condition-components',
    deprecatedIn: '6.8.0',
    removedIn: '6.8.0',
    description:
        'sw-condition-line-item-in-category and sw-condition-line-item-purchase-price were removed together with their blocks.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-rule-condition-components'),
    ],
    usage: [
        'sw_condition_line_item_in_category_field_operator',
        'sw_condition_line_item_in_category_field_identifiers',
        'sw_condition_line_item_in_category_field_result_item',
        'sw_condition_line_item_in_category_field_result_item_content',
        'sw_condition_line_item_in_category_field_description',
        'sw_condition_line_item_in_category_field_description_default',
        'sw_condition_line_item_purchase_price_field_gross_net',
        'sw_condition_line_item_purchase_price_field_operator',
        'sw_condition_line_item_purchase_price_field_amount',
    ].map((from) => {
        return removeTemplateBlock({
            from,
            message:
                'Use sw-condition-generic (sw-condition-generic-line-item for line items), ' +
                'it renders the condition from its backend configuration.',
        });
    }),
});
