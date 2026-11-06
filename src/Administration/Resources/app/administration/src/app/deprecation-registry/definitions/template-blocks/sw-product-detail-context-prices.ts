/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-product-detail-context-prices',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-product-detail-context-prices that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks')],
    usage: [
        removeTemplateBlock({
            from: 'sw_product_detail_prices_empty_state_image',
            moveTo: 'sw_product_detail_prices_price_empty_state (pass the icon and texts as props of mt-empty-state)',
            components: ['sw-product-detail-context-prices'],
        }),
        removeTemplateBlock({
            from: 'sw_product_detail_prices_price_empty_state_text',
            moveTo: 'sw_product_detail_prices_price_empty_state (pass the icon and texts as props of mt-empty-state)',
            components: ['sw-product-detail-context-prices'],
        }),
        removeTemplateBlock({
            from: 'sw_product_detail_prices_price_empty_state_text_child',
            moveTo: 'sw_product_detail_prices_price_empty_state (pass the icon and texts as props of mt-empty-state)',
            components: ['sw-product-detail-context-prices'],
        }),
        removeTemplateBlock({
            from: 'sw_product_detail_prices_price_empty_state_text_empty',
            moveTo: 'sw_product_detail_prices_price_empty_state (pass the icon and texts as props of mt-empty-state)',
            components: ['sw-product-detail-context-prices'],
        }),
        removeTemplateBlock({
            from: 'sw_product_detail_prices_price_empty_state_text_inherited',
            moveTo: 'sw_product_detail_prices_price_empty_state (pass the icon and texts as props of mt-empty-state)',
            components: ['sw-product-detail-context-prices'],
        }),
        removeTemplateBlock({
            from: 'sw_product_detail_prices_price_empty_state_text_link',
            moveTo: 'sw_product_detail_prices_price_empty_state (pass the icon and texts as props of mt-empty-state)',
            components: ['sw-product-detail-context-prices'],
        }),
        removeTemplateBlock({
            from: 'sw_product_detail_prices_price_empty_state_text_not_inherited',
            moveTo: 'sw_product_detail_prices_price_empty_state (pass the icon and texts as props of mt-empty-state)',
            components: ['sw-product-detail-context-prices'],
        }),
    ],
});
