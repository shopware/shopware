/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-customer-detail',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-customer-detail that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches')],
    usage: [
        removeTemplateBlock({
            from: 'sw_customer_detail_content_tab_addresses',
            moveTo: 'sw_customer_detail_content_mt_tabs and add your tab to its customerDetailTabs computed',
            components: ['sw-customer-detail'],
        }),
        removeTemplateBlock({
            from: 'sw_customer_detail_content_tab_after',
            moveTo: 'sw_customer_detail_content_mt_tabs and add your tab to its customerDetailTabs computed',
            components: ['sw-customer-detail'],
        }),
        removeTemplateBlock({
            from: 'sw_customer_detail_content_tab_general',
            moveTo: 'sw_customer_detail_content_mt_tabs and add your tab to its customerDetailTabs computed',
            components: ['sw-customer-detail'],
        }),
        removeTemplateBlock({
            from: 'sw_customer_detail_content_tab_order',
            moveTo: 'sw_customer_detail_content_mt_tabs and add your tab to its customerDetailTabs computed',
            components: ['sw-customer-detail'],
        }),
    ],
});
