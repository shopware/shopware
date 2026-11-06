/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-order-create',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-order-create that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches')],
    usage: [
        removeTemplateBlock({
            from: 'sw_order_create_content_tabs_details',
            moveTo: 'sw_order_create_content_mt_tabs and add your tab to its orderCreateTabs computed',
            components: ['sw-order-create'],
        }),
        removeTemplateBlock({
            from: 'sw_order_create_content_tabs_general',
            moveTo: 'sw_order_create_content_mt_tabs and add your tab to its orderCreateTabs computed',
            components: ['sw-order-create'],
        }),
    ],
});
