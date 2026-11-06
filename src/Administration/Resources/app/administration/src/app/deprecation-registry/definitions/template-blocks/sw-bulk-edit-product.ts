/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-bulk-edit-product',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-bulk-edit-product that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks')],
    usage: [
        removeTemplateBlock({
            from: 'sw_bulk_edit_product_content_meansures_packaging_card',
            moveTo: 'sw_bulk_edit_product_content_measurement_card',
            components: ['sw-bulk-edit-product'],
        }),
    ],
});
