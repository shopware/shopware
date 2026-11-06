/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-product-stream-filter',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-product-stream-filter that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks')],
    usage: [
        removeTemplateBlock({
            from: 'sw_product_stream_filter',
            moveTo: 'sw_condition_base',
            components: ['sw-product-stream-filter'],
        }),
        removeTemplateBlock({
            from: 'sw_product_stream_filter_container',
            moveTo: 'sw_condition_base_content',
            components: ['sw-product-stream-filter'],
        }),
    ],
});
