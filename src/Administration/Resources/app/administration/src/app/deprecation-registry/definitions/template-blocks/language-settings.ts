/**
 * @sw-package framework
 */

import { templateBlockMigration, upgradeGuide } from '../helpers';
import { moveBlocks, removeBlocks } from './block-steps';

export default templateBlockMigration({
    id: 'template-block.language-settings',
    deprecatedIn: '6.8.0',
    removedIn: '6.8.0',
    description:
        'The language list lost its sidebar filter and some blocks of its smart bar and grid; ' +
        'the language detail page lost its language info block.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#sw-settings-language-list'),
        upgradeGuide('UPGRADE-6.8.md#sw-settings-language-detail'),
    ],
    usage: [
        ...moveBlocks({
            'sw-settings-language-list': {
                sw_settings_language_list_smart_bar_header_title: ['sw_settings_language_list_smart_bar_header_amount'],
                sw_settings_language_list_content_list_columns: ['sw_settings_language_list_content_list_column_id'],
                sw_settings_language_list_delete_modal: ['sw_settings_language_list_content_list_delete_confirm_action'],
            },
        }),
        ...removeBlocks('sw-settings-language-list', [
            'sw_settings_language_list_grid_sidebar_filter',
            'sw_settings_language_list_grid_sidebar_filter_content',
            'sw_settings_language_list_grid_sidebar_filter_content_inherited_languages',
            'sw_settings_language_list_grid_sidebar_filter_content_root_languages',
            'sw_settings_language_list_grid_sidebar_filter_header',
            'sw_settings_language_list_grid_sidebar_filter_header_icon',
            'sw_settings_language_list_grid_sidebar_filter_header_icon_expanded',
            'sw_settings_language_list_grid_sidebar_filter_header_icon_not_expanded',
            'sw_settings_language_list_grid_sidebar_filter_header_title',
        ]),
        ...removeBlocks('sw-settings-language-detail', ['sw_settings_language_detail_content_language_info']),
    ],
});
