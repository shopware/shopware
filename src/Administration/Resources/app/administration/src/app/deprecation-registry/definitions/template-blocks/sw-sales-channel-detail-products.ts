/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-sales-channel-detail-products',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-sales-channel-detail-products that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks')],
    usage: [
        removeTemplateBlock({
            from: 'sw_sales_channel_detail_products_card_section_primary_image',
            moveTo: 'sw_sales_channel_detail_products_card_section_primary_empty_state',
            components: ['sw-sales-channel-detail-products'],
        }),
        removeTemplateBlock({
            from: 'sw_sales_channel_detail_products_empty_state_image',
            moveTo: 'sw_sales_channel_detail_products_empty_state',
            components: ['sw-sales-channel-detail-products'],
        }),
    ],
});
