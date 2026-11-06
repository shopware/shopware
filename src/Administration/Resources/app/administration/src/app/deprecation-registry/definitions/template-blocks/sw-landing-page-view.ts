/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-landing-page-view',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-landing-page-view that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches')],
    usage: [
        removeTemplateBlock({
            from: 'sw_landing_page_view_tabs_cms',
            moveTo: 'sw_landing_page_view_mt_tabs and add your tab to its landingPageViewTabs computed',
            components: ['sw-landing-page-view'],
        }),
        removeTemplateBlock({
            from: 'sw_landing_page_view_tabs_general',
            moveTo: 'sw_landing_page_view_mt_tabs and add your tab to its landingPageViewTabs computed',
            components: ['sw-landing-page-view'],
        }),
    ],
});
