/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.agentic-commerce',
    deprecatedIn: '6.8.0',
    removedIn: '6.8.0',
    description: 'The Agentic Commerce Administration UI moved to the SwagAgenticCommerce plugin, together with its blocks.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#agentic-commerce-administration-ui-moved-to-swagagenticcommerce'),
    ],
    usage: [
        'sw_sales_channel_detail_agentic_commerce_deprecation_banner',
        'sw_sales_channel_detail_agentic_commerce_tracking',
    ].map((from) => {
        return removeTemplateBlock({
            from,
            message: 'Install the SwagAgenticCommerce plugin and remove this override.',
        });
    }),
});
