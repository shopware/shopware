/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-profile-index',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-profile-index that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches')],
    usage: [
        removeTemplateBlock({
            from: 'sw_profile_index_tabs_item_general',
            moveTo: 'sw_profile_index_mt_tabs and add your tab to its profileTabs computed',
            components: ['sw-profile-index'],
        }),
        removeTemplateBlock({
            from: 'sw_profile_index_tabs_item_search_preferences',
            moveTo: 'sw_profile_index_mt_tabs and add your tab to its profileTabs computed',
            components: ['sw-profile-index'],
        }),
    ],
});
