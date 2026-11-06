/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-flow-detail',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-flow-detail that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches')],
    usage: [
        removeTemplateBlock({
            from: 'sw_flow_tabs_header_builder',
            moveTo: 'sw_flow_tabs_header_mt_tabs and add your tab to its flowDetailTabs computed',
            components: ['sw-flow-detail'],
        }),
        removeTemplateBlock({
            from: 'sw_flow_tabs_header_extension',
            moveTo: 'sw_flow_tabs_header_mt_tabs and add your tab to its flowDetailTabs computed',
            components: ['sw-flow-detail'],
            onlyInComponents: true,
        }),
        removeTemplateBlock({
            from: 'sw_flow_tabs_header_general',
            moveTo: 'sw_flow_tabs_header_mt_tabs and add your tab to its flowDetailTabs computed',
            components: ['sw-flow-detail'],
        }),
    ],
});
