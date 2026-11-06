/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-product-packaging-form',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-product-packaging-form that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks')],
    usage: [
        removeTemplateBlock({
            from: 'sw_product_packaging_form_height_field',
            moveTo: 'sw_product_measurement_form_height_field of sw-product-measurement-form',
            components: ['sw-product-packaging-form'],
        }),
        removeTemplateBlock({
            from: 'sw_product_packaging_form_width_field',
            moveTo: 'sw_product_measurement_form_width_field of sw-product-measurement-form',
            components: ['sw-product-packaging-form'],
        }),
        removeTemplateBlock({
            from: 'sw_product_settings_form_length_field',
            moveTo: 'sw_product_measurement_form_length_field of sw-product-measurement-form',
            components: ['sw-product-packaging-form'],
        }),
        removeTemplateBlock({
            from: 'sw_product_settings_form_weight_field',
            moveTo: 'sw_product_measurement_form_weight_field of sw-product-measurement-form',
            components: ['sw-product-packaging-form'],
        }),
    ],
});
