/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-product-detail-cross-selling',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-product-detail-cross-selling that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks')],
    usage: [
        removeTemplateBlock({
            from: 'sw_product_detail_cross_selling_empty_state_actions',
            moveTo: 'sw_product_detail_cross_selling_empty_state (pass the icon and texts as props of mt-empty-state)',
            components: ['sw-product-detail-cross-selling'],
        }),
        removeTemplateBlock({
            from: 'sw_product_detail_cross_selling_empty_state_content',
            moveTo: 'sw_product_detail_cross_selling_empty_state (pass the icon and texts as props of mt-empty-state)',
            components: ['sw-product-detail-cross-selling'],
        }),
        removeTemplateBlock({
            from: 'sw_product_detail_cross_selling_empty_state_content_child',
            moveTo: 'sw_product_detail_cross_selling_empty_state (pass the icon and texts as props of mt-empty-state)',
            components: ['sw-product-detail-cross-selling'],
        }),
        removeTemplateBlock({
            from: 'sw_product_detail_cross_selling_empty_state_content_child_inherited',
            moveTo: 'sw_product_detail_cross_selling_empty_state (pass the icon and texts as props of mt-empty-state)',
            components: ['sw-product-detail-cross-selling'],
        }),
        removeTemplateBlock({
            from: 'sw_product_detail_cross_selling_empty_state_content_child_inherited_link',
            moveTo: 'sw_product_detail_cross_selling_empty_state (pass the icon and texts as props of mt-empty-state)',
            components: ['sw-product-detail-cross-selling'],
        }),
        removeTemplateBlock({
            from: 'sw_product_detail_cross_selling_empty_state_content_child_not_inherited',
            moveTo: 'sw_product_detail_cross_selling_empty_state (pass the icon and texts as props of mt-empty-state)',
            components: ['sw-product-detail-cross-selling'],
        }),
        removeTemplateBlock({
            from: 'sw_product_detail_cross_selling_empty_state_content_empty',
            moveTo: 'sw_product_detail_cross_selling_empty_state (pass the icon and texts as props of mt-empty-state)',
            components: ['sw-product-detail-cross-selling'],
        }),
        removeTemplateBlock({
            from: 'sw_product_detail_cross_selling_empty_state_icon',
            moveTo: 'sw_product_detail_cross_selling_empty_state (pass the icon and texts as props of mt-empty-state)',
            components: ['sw-product-detail-cross-selling'],
        }),
    ],
});
