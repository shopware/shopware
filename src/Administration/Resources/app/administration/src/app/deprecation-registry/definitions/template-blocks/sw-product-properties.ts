/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-product-properties',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-product-properties that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks')],
    usage: [
        removeTemplateBlock({
            from: 'sw_product_properties_empty_state_image',
            moveTo: 'sw_product_properties_empty_state',
            components: ['sw-product-properties'],
        }),
        removeTemplateBlock({
            from: 'sw_product_properties_filled_state_body_empty_state_image',
            moveTo: 'sw_product_properties_filled_state_body_empty_state',
            components: ['sw-product-properties'],
        }),
    ],
});
