/**
 * @sw-package framework
 */

import { templateBlockMigration, upgradeGuide } from '../helpers';
import { moveBlocks } from './block-steps';

export default templateBlockMigration({
    id: 'template-block.product-packaging',
    deprecatedIn: '6.8.0',
    removedIn: '6.8.0',
    description:
        'The measurement fields moved from sw-product-packaging-form to sw-product-measurement-form, ' +
        'and the bulk edit packaging card became the measurement card.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#sw-product-packaging-form'),
        upgradeGuide('UPGRADE-6.8.md#sw-bulk-edit-product'),
    ],
    usage: [
        ...moveBlocks(
            {
                'sw-product-packaging-form': {
                    sw_product_measurement_form_width_field: ['sw_product_packaging_form_width_field'],
                    sw_product_measurement_form_height_field: ['sw_product_packaging_form_height_field'],
                    sw_product_measurement_form_length_field: ['sw_product_settings_form_length_field'],
                    sw_product_measurement_form_weight_field: ['sw_product_settings_form_weight_field'],
                },
            },
            'The block is part of sw-product-measurement-form, override that component instead.',
        ),
        ...moveBlocks({
            'sw-bulk-edit-product': {
                sw_bulk_edit_product_content_measurement_card: ['sw_bulk_edit_product_content_meansures_packaging_card'],
            },
        }),
    ],
});
