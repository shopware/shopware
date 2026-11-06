/**
 * @sw-package framework
 */

import { renameTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-cms-el-config-product-box',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-cms-el-config-product-box that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#renamed-cms-element-config-blocks')],
    usage: [
        renameTemplateBlock({
            from: 'sw_entity_single_select_base_results_list_result_label',
            to: 'sw_cms_element_product_box_config_product_select_result_item_inner',
            components: ['sw-cms-el-config-product-box'],
            onlyInComponents: true,
        }),
    ],
});
