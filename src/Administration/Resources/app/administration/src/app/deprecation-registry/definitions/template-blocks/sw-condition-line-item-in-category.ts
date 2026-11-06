/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-condition-line-item-in-category',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-condition-line-item-in-category that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks')],
    usage: [
        removeTemplateBlock({
            from: 'sw_condition_line_item_in_category_field_operator',
            moveTo: 'sw-condition-generic',
            components: ['sw-condition-line-item-in-category'],
        }),
        removeTemplateBlock({
            from: 'sw_condition_line_item_in_category_field_identifiers',
            moveTo: 'sw-condition-generic',
            components: ['sw-condition-line-item-in-category'],
        }),
        removeTemplateBlock({
            from: 'sw_condition_line_item_in_category_field_result_item',
            moveTo: 'sw-condition-generic',
            components: ['sw-condition-line-item-in-category'],
        }),
        removeTemplateBlock({
            from: 'sw_condition_line_item_in_category_field_result_item_content',
            moveTo: 'sw-condition-generic',
            components: ['sw-condition-line-item-in-category'],
        }),
        removeTemplateBlock({
            from: 'sw_condition_line_item_in_category_field_description',
            moveTo: 'sw-condition-generic',
            components: ['sw-condition-line-item-in-category'],
        }),
        removeTemplateBlock({
            from: 'sw_condition_line_item_in_category_field_description_default',
            moveTo: 'sw-condition-generic',
            components: ['sw-condition-line-item-in-category'],
        }),
    ],
});
