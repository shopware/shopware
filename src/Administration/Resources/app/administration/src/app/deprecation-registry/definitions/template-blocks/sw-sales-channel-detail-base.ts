/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-sales-channel-detail-base',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-sales-channel-detail-base that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks')],
    usage: [
        removeTemplateBlock({
            from: 'sw_sales_channel_detail_agentic_commerce_tracking',
            moveTo: 'the SwagAgenticCommerce plugin',
            components: ['sw-sales-channel-detail-base'],
        }),
    ],
});
