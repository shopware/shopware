/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-settings-search-live-search',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-settings-search-live-search that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks')],
    usage: [
        removeTemplateBlock({
            from: 'sw_settings_search_view_live_search_search_icon',
            moveTo: 'sw_settings_search_view_live_search_input',
            components: ['sw-settings-search-live-search'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_search_view_live_search_search_icon_wrapper',
            moveTo: 'sw_settings_search_view_live_search_input',
            components: ['sw-settings-search-live-search'],
        }),
    ],
});
