/**
 * @sw-package framework
 */

import { templateBlockMigration, upgradeGuide } from '../helpers';
import { moveBlocks } from './block-steps';

export default templateBlockMigration({
    id: 'template-block.search-settings',
    deprecatedIn: '6.8.0',
    removedIn: '6.8.0',
    description:
        'The search settings lost the image blocks of their empty states and some blocks of the live search, ' +
        'the search index and the searchable content example link.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#sw-settings-search-excluded-search-terms'),
        upgradeGuide('UPGRADE-6.8.md#sw-settings-search-searchable-content-general'),
        upgradeGuide('UPGRADE-6.8.md#sw-settings-search-searchable-content-customfields'),
        upgradeGuide('UPGRADE-6.8.md#sw-settings-search-live-search'),
        upgradeGuide('UPGRADE-6.8.md#sw-settings-search-search-index'),
        upgradeGuide('UPGRADE-6.8.md#sw-settings-search-searchable-content'),
    ],
    usage: moveBlocks({
        'sw-settings-search-excluded-search-terms': {
            sw_settings_search_excluded_search_terms_empty_state: [
                'sw_settings_search_excluded_search_terms_empty_state_image',
            ],
        },
        'sw-settings-search-searchable-content-general': {
            sw_settings_search_searchable_content_general_empty_state: [
                'sw_settings_search_searchable_content_general_state_image',
            ],
        },
        'sw-settings-search-searchable-content-customfields': {
            sw_settings_search_searchable_content_customfields_empty_state: [
                'sw_settings_search_searchable_content_customfields_state_image',
            ],
        },
        'sw-settings-search-live-search': {
            sw_settings_search_view_live_search_input: [
                'sw_settings_search_view_live_search_search_icon',
                'sw_settings_search_view_live_search_search_icon_wrapper',
            ],
        },
        'sw-settings-search-search-index': {
            sw_settings_search_search_index_rebuild_progress_bar: ['sw_settings_search_search_index_rebuild_progress_text'],
            sw_settings_search_search_index_warning_bottom: ['sw_settings_search_search_index_warning_top'],
        },
        'sw-settings-search-searchable-content': {
            sw_settings_search_searchable_show_example_link: [
                'sw_settings_search_searchable_show_example',
                'sw_settings_search_searchable_show_example_link_element',
            ],
        },
    }),
});
