/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-settings-search-searchable-content',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-settings-search-searchable-content that Shopware 6.8 renames or removes.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches'),
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks'),
    ],
    usage: [
        removeTemplateBlock({
            from: 'sw_settings_search_searchable_content_general_tab_item',
            moveTo: 'sw_settings_search_searchable_content_mt_general_tab_item (the markup changed)',
            components: ['sw-settings-search-searchable-content'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_search_searchable_content_general_tab_title',
            moveTo: 'sw_settings_search_searchable_content_mt_tabs and add your tab to its searchableContentTabs computed',
            components: ['sw-settings-search-searchable-content'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_search_searchable_show_example',
            moveTo: 'sw_settings_search_searchable_show_example_link',
            components: ['sw-settings-search-searchable-content'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_search_searchable_show_example_link_element',
            moveTo: 'sw_settings_search_searchable_show_example_link',
            components: ['sw-settings-search-searchable-content'],
        }),
    ],
});
