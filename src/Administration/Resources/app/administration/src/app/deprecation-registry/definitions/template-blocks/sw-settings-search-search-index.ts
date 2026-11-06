/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-settings-search-search-index',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-settings-search-search-index that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks')],
    usage: [
        removeTemplateBlock({
            from: 'sw_settings_search_search_index_rebuild_progress_text',
            moveTo: 'sw_settings_search_search_index_rebuild_progress_bar',
            components: ['sw-settings-search-search-index'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_search_search_index_warning_top',
            moveTo: 'sw_settings_search_search_index_warning_bottom',
            components: ['sw-settings-search-search-index'],
        }),
    ],
});
