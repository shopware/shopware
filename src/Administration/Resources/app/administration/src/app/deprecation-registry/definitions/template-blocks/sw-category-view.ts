/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-category-view',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-category-view that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches')],
    usage: [
        removeTemplateBlock({
            from: 'sw_category_view_tabs_cms',
            moveTo: 'sw_category_view_mt_tabs and add your tab to its categoryViewTabs computed',
            components: ['sw-category-view'],
        }),
        removeTemplateBlock({
            from: 'sw_category_view_tabs_general',
            moveTo: 'sw_category_view_mt_tabs and add your tab to its categoryViewTabs computed',
            components: ['sw-category-view'],
        }),
        removeTemplateBlock({
            from: 'sw_category_view_tabs_products',
            moveTo: 'sw_category_view_mt_tabs and add your tab to its categoryViewTabs computed',
            components: ['sw-category-view'],
        }),
        removeTemplateBlock({
            from: 'sw_category_view_tabs_seo',
            moveTo: 'sw_category_view_mt_tabs and add your tab to its categoryViewTabs computed',
            components: ['sw-category-view'],
        }),
    ],
});
