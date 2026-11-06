/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-order-detail',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-order-detail that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches')],
    usage: [
        removeTemplateBlock({
            from: 'sw_order_detail_content_tabs_details',
            moveTo: 'sw_order_detail_content_mt_tabs and add your tab to its orderDetailTabs computed',
            components: ['sw-order-detail'],
        }),
        removeTemplateBlock({
            from: 'sw_order_detail_content_tabs_documents',
            moveTo: 'sw_order_detail_content_mt_tabs and add your tab to its orderDetailTabs computed',
            components: ['sw-order-detail'],
        }),
        removeTemplateBlock({
            from: 'sw_order_detail_content_tabs_extension',
            moveTo: 'sw_order_detail_content_mt_tabs and add your tab to its orderDetailTabs computed',
            components: ['sw-order-detail'],
        }),
        removeTemplateBlock({
            from: 'sw_order_detail_content_tabs_general',
            moveTo: 'sw_order_detail_content_mt_tabs and add your tab to its orderDetailTabs computed',
            components: ['sw-order-detail'],
        }),
    ],
});
