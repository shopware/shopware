/**
 * @sw-package framework
 */

import { renameTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-product-cross-selling-assignment',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-product-cross-selling-assignment that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#renamed-cms-element-config-blocks')],
    usage: [
        renameTemplateBlock({
            from: 'sw_entity_single_select_base_results_list_result_label',
            to: 'sw_product_cross_selling_assignment_select_result_item_inner',
            components: ['sw-product-cross-selling-assignment'],
            onlyInComponents: true,
        }),
    ],
});
