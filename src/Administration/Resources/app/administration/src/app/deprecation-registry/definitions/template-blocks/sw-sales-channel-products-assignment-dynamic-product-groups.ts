/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-sales-channel-products-assignment-dynamic-product-groups',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'Twig blocks of sw-sales-channel-products-assignment-dynamic-product-groups that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks')],
    usage: [
        removeTemplateBlock({
            from: 'sw_sales_channel_products_assignment_dynamic_product_groups_listing_empty_icon',
            moveTo: 'sw_sales_channel_products_assignment_dynamic_product_groups_listing_empty (pass the icon and texts as props of mt-empty-state)',
            components: ['sw-sales-channel-products-assignment-dynamic-product-groups'],
        }),
    ],
});
