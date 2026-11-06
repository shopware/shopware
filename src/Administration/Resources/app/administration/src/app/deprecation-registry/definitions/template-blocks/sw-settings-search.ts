/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-settings-search',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-settings-search that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches')],
    usage: [
        removeTemplateBlock({
            from: 'sw_setting_search_tabs_after',
            moveTo: 'sw_settings_search_mt_tabs_header and add your tab to its settingsSearchTabs computed',
            components: ['sw-settings-search'],
        }),
        removeTemplateBlock({
            from: 'sw_setting_search_tabs_general',
            moveTo: 'sw_settings_search_mt_tabs_header and add your tab to its settingsSearchTabs computed',
            components: ['sw-settings-search'],
        }),
        removeTemplateBlock({
            from: 'sw_setting_search_tabs_live_search',
            moveTo: 'sw_settings_search_mt_tabs_header and add your tab to its settingsSearchTabs computed',
            components: ['sw-settings-search'],
        }),
    ],
});
