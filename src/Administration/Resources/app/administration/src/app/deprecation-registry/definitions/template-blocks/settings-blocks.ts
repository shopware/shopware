/**
 * @sw-package framework
 */

import { removeTemplateBlocks, renameTemplateBlocks, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.settings-6-8',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of the settings modules that Shopware 6.8 renames or removes.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches'),
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks'),
    ],
    usage: [
        ...removeTemplateBlocks({
            components: ['sw-integration-list'],
            blocks: {
                sw_integration_list_introduction: null,
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-settings-country-detail', 'sw-settings-country-create'],
            blocks: {
                sw_setting_country_tabs_address_handling:
                    'sw_settings_country_mt_tabs_header and add your tab to its countryTabs computed',
                sw_setting_country_tabs_extension:
                    'sw_settings_country_mt_tabs_header and add your tab to its countryTabs computed',
                sw_setting_country_tabs_setting:
                    'sw_settings_country_mt_tabs_header and add your tab to its countryTabs computed',
                sw_setting_country_tabs_state:
                    'sw_settings_country_mt_tabs_header and add your tab to its countryTabs computed',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-settings-customer-group-detail', 'sw-settings-customer-group-create'],
            blocks: {
                sw_settings_customer_group_detail_content_card_display_gross:
                    'sw_settings_customer_group_detail_content_card_tax_display',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-settings-language-detail'],
            blocks: {
                sw_settings_language_detail_content_language_info: null,
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-settings-language-list'],
            blocks: {
                sw_settings_language_list_content_list_column_id: 'sw_settings_language_list_content_list_columns',
                sw_settings_language_list_content_list_delete_confirm_action: 'sw_settings_language_list_delete_modal',
                sw_settings_language_list_grid_sidebar_filter: null,
                sw_settings_language_list_grid_sidebar_filter_content: null,
                sw_settings_language_list_grid_sidebar_filter_content_inherited_languages: null,
                sw_settings_language_list_grid_sidebar_filter_content_root_languages: null,
                sw_settings_language_list_grid_sidebar_filter_header: null,
                sw_settings_language_list_grid_sidebar_filter_header_icon: null,
                sw_settings_language_list_grid_sidebar_filter_header_icon_expanded: null,
                sw_settings_language_list_grid_sidebar_filter_header_icon_not_expanded: null,
                sw_settings_language_list_grid_sidebar_filter_header_title: null,
                sw_settings_language_list_smart_bar_header_amount: 'sw_settings_language_list_smart_bar_header_title',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-settings-listing'],
            blocks: {
                sw_settings_listing_content_card_view_options_card_empty_state_icon:
                    'sw_settings_listing_content_card_view_options_card_empty_state (pass the icon and texts as props of mt-empty-state)',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-settings-listing-option-criteria-grid'],
            blocks: {
                sw_settings_listing_option_criteria_card_empty_state_icon:
                    'sw_settings_listing_option_criteria_card_empty_state (pass the icon and texts as props of mt-empty-state)',
            },
        }),
        ...renameTemplateBlocks({
            components: ['sw-settings-logging-entry-info', 'sw-settings-logging-mail-sent-info'],
            sharedNames: ['sw_settings_logging_entry_info_content'],
            blocks: {
                sw_settings_logging_entry_info_content: 'sw_settings_logging_entry_info_mt_content',
                sw_settings_logging_entry_info_raw_content: 'sw_settings_logging_entry_info_mt_raw_content',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-settings-logging-entry-info', 'sw-settings-logging-mail-sent-info'],
            sharedNames: ['sw_settings_logging_entry_info_tab_items'],
            blocks: {
                sw_settings_logging_entry_info_tab_items:
                    'sw_settings_logging_entry_info_mt_tabs and add your tab to its entryInfoTabs computed',
            },
        }),
        ...renameTemplateBlocks({
            components: ['sw-settings-logging-mail-sent-info'],
            blocks: {
                sw_settings_logging_mail_sent_content_mailbody: 'sw_settings_logging_mail_sent_mt_content_mailbody',
                sw_settings_logging_mail_sent_content_mailbody_html:
                    'sw_settings_logging_mail_sent_mt_content_mailbody_html',
                sw_settings_logging_mail_sent_content_mailbody_plain:
                    'sw_settings_logging_mail_sent_mt_content_mailbody_plain',
                sw_settings_logging_mail_sent_content_mailbody_title:
                    'sw_settings_logging_mail_sent_mt_content_mailbody_title',
                sw_settings_logging_mail_sent_content_recipients: 'sw_settings_logging_mail_sent_mt_content_recipients',
                sw_settings_logging_mail_sent_content_recipients_title:
                    'sw_settings_logging_mail_sent_mt_content_recipients_title',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-settings-logging-mail-sent-info'],
            sharedNames: ['sw_settings_logging_entry_info_tab_items'],
            blocks: {
                sw_settings_logging_entry_info_tab_items:
                    'sw_settings_logging_entry_info_mt_tabs and add your tab to its entryInfoTabs computed',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-settings-mailer'],
            blocks: {
                sw_settings_mailer_headline_agent: 'sw_settings_mailer_agent_options',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-settings-product-feature-sets-values-card'],
            blocks: {
                sw_product_feature_set_card_empty_state_image:
                    'sw_product_feature_set_card_empty_state (pass the icon and texts as props of mt-empty-state)',
                sw_product_feature_set_card_empty_state_label:
                    'sw_product_feature_set_card_empty_state (pass the icon and texts as props of mt-empty-state)',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-settings-rule-detail'],
            blocks: {
                sw_settings_rule_detail_tab_items:
                    'sw_settings_rule_detail_mt_tabs and add your tab to its ruleDetailTabs computed',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-settings-search'],
            blocks: {
                sw_setting_search_tabs_after:
                    'sw_settings_search_mt_tabs_header and add your tab to its settingsSearchTabs computed',
                sw_setting_search_tabs_general:
                    'sw_settings_search_mt_tabs_header and add your tab to its settingsSearchTabs computed',
                sw_setting_search_tabs_live_search:
                    'sw_settings_search_mt_tabs_header and add your tab to its settingsSearchTabs computed',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-settings-search-excluded-search-terms'],
            blocks: {
                sw_settings_search_excluded_search_terms_empty_state_image:
                    'sw_settings_search_excluded_search_terms_empty_state',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-settings-search-live-search'],
            blocks: {
                sw_settings_search_view_live_search_search_icon: 'sw_settings_search_view_live_search_input',
                sw_settings_search_view_live_search_search_icon_wrapper: 'sw_settings_search_view_live_search_input',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-settings-search-search-index'],
            blocks: {
                sw_settings_search_search_index_rebuild_progress_text:
                    'sw_settings_search_search_index_rebuild_progress_bar',
                sw_settings_search_search_index_warning_top: 'sw_settings_search_search_index_warning_bottom',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-settings-search-searchable-content'],
            blocks: {
                sw_settings_search_searchable_content_general_tab_item:
                    'sw_settings_search_searchable_content_mt_general_tab_item (the markup changed)',
                sw_settings_search_searchable_content_general_tab_title:
                    'sw_settings_search_searchable_content_mt_tabs and add your tab to its searchableContentTabs computed',
                sw_settings_search_searchable_show_example: 'sw_settings_search_searchable_show_example_link',
                sw_settings_search_searchable_show_example_link_element: 'sw_settings_search_searchable_show_example_link',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-settings-search-searchable-content-customfields'],
            blocks: {
                sw_settings_search_searchable_content_customfields_state_image:
                    'sw_settings_search_searchable_content_customfields_empty_state',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-settings-search-searchable-content-general'],
            blocks: {
                sw_settings_search_searchable_content_general_state_image:
                    'sw_settings_search_searchable_content_general_empty_state',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-settings-snippet-set-list'],
            blocks: {
                sw_settings_snippet_set_list_card_btn_copy:
                    'sw_settings_snippet_set_list_card_list (the header actions are in the smart-bar-actions slot, which has no block)',
                sw_settings_snippet_set_list_card_btn_copy_icon:
                    'sw_settings_snippet_set_list_card_list (the header actions are in the smart-bar-actions slot, which has no block)',
                sw_settings_snippet_set_list_card_copy_context_divider:
                    'sw_settings_snippet_set_list_card_list (the header actions are in the smart-bar-actions slot, which has no block)',
                sw_settings_snippet_set_list_card_copy_context_menu:
                    'sw_settings_snippet_set_list_card_list (the header actions are in the smart-bar-actions slot, which has no block)',
                sw_settings_snippet_set_list_card_copy_context_menu_items:
                    'sw_settings_snippet_set_list_card_list (the header actions are in the smart-bar-actions slot, which has no block)',
                sw_settings_snippet_set_list_card_list_btn_copy_split:
                    'sw_settings_snippet_set_list_card_list (the header actions are in the smart-bar-actions slot, which has no block)',
                sw_settings_snippet_set_list_card_list_container:
                    'sw_settings_snippet_set_list_card_list (the header actions are in the smart-bar-actions slot, which has no block)',
                sw_settings_snippet_set_list_card_list_container_header:
                    'sw_settings_snippet_set_list_card_list (the header actions are in the smart-bar-actions slot, which has no block)',
                sw_settings_snippet_set_list_card_list_container_header_btn_add_set:
                    'sw_settings_snippet_set_list_card_list (the header actions are in the smart-bar-actions slot, which has no block)',
                sw_settings_snippet_set_list_card_list_container_header_btn_edit_set:
                    'sw_settings_snippet_set_list_card_list (the header actions are in the smart-bar-actions slot, which has no block)',
                sw_settings_snippet_set_list_card_list_container_header_btn_export:
                    'sw_settings_snippet_set_list_card_list (the header actions are in the smart-bar-actions slot, which has no block)',
                sw_settings_snippet_set_list_card_list_container_header_btn_split:
                    'sw_settings_snippet_set_list_card_list (the header actions are in the smart-bar-actions slot, which has no block)',
                sw_settings_snippet_set_list_card_list_grid_column_base_action:
                    'sw_settings_snippet_set_list_card_list_grid',
                sw_settings_snippet_set_list_card_list_grid_column_base_action_btn_clone:
                    'sw_settings_snippet_set_list_card_list_grid',
                sw_settings_snippet_set_list_card_list_grid_column_base_action_btn_delete:
                    'sw_settings_snippet_set_list_card_list_grid',
                sw_settings_snippet_set_list_card_list_grid_column_base_action_btn_edit:
                    'sw_settings_snippet_set_list_card_list_grid',
                sw_settings_snippet_set_list_card_list_grid_column_base_action_buttons:
                    'sw_settings_snippet_set_list_card_list_grid',
                sw_settings_snippet_set_list_card_list_grid_column_base_file: 'sw_settings_snippet_set_list_card_list_grid',
                sw_settings_snippet_set_list_card_list_grid_column_base_file_editor:
                    'sw_settings_snippet_set_list_card_list_grid',
                sw_settings_snippet_set_list_card_list_grid_column_changed: 'sw_settings_snippet_set_list_card_list_grid',
                sw_settings_snippet_set_list_card_list_grid_column_changed_date:
                    'sw_settings_snippet_set_list_card_list_grid',
                sw_settings_snippet_set_list_card_list_grid_column_name: 'sw_settings_snippet_set_list_card_list_grid',
                sw_settings_snippet_set_list_card_list_grid_column_name_editor:
                    'sw_settings_snippet_set_list_card_list_grid',
                sw_settings_snippet_set_list_card_list_grid_column_name_link: 'sw_settings_snippet_set_list_card_list_grid',
                sw_settings_snippet_set_list_card_list_grid_pagination: 'sw_settings_snippet_set_list_card_list_grid',
                sw_settings_snippet_set_list_card_list_grid_pagination_bar: 'sw_settings_snippet_set_list_card_list_grid',
                sw_settings_snippet_set_list_card_list_grid_template: 'sw_settings_snippet_set_list_card_list_grid',
                sw_settings_snippet_set_list_card_title: 'sw_settings_snippet_set_list_card_list',
                sw_settings_snippet_set_list_clone_modal_cancel: 'sw_settings_snippet_set_list_card_list_grid',
                sw_settings_snippet_set_list_clone_modal_confirm: 'sw_settings_snippet_set_list_card_list_grid',
                sw_settings_snippet_set_list_clone_modal_confirm_delete_text: 'sw_settings_snippet_set_list_card_list_grid',
                sw_settings_snippet_set_list_clone_modal_footer: 'sw_settings_snippet_set_list_card_list_grid',
                sw_settings_snippet_set_list_columns_clone_modal: 'sw_settings_snippet_set_list_card_list_grid',
                sw_settings_snippet_set_list_columns_delete_modal: 'sw_settings_snippet_set_list_card_list_grid',
                sw_settings_snippet_set_list_delete_modal_cancel: 'sw_settings_snippet_set_list_card_list_grid',
                sw_settings_snippet_set_list_delete_modal_confirm: 'sw_settings_snippet_set_list_card_list_grid',
                sw_settings_snippet_set_list_delete_modal_confirm_delete_text: 'sw_settings_snippet_set_list_card_list_grid',
                sw_settings_snippet_set_list_delete_modal_footer: 'sw_settings_snippet_set_list_card_list_grid',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-settings-storefront-configuration'],
            sharedNames: [
                'sw_settings_storefront',
                'sw_settings_storefront_settings_icon_cache',
                'sw_settings_storefront_settings_speculation_rules',
                'sw_settings_storefront_settings_theme_async',
            ],
            blocks: {
                sw_settings_storefront: 'the block of the same name in sw-settings-storefront-index',
                sw_settings_storefront_settings_icon_cache: 'the block of the same name in sw-settings-storefront-index',
                sw_settings_storefront_settings_speculation_rules:
                    'the block of the same name in sw-settings-storefront-index',
                sw_settings_storefront_settings_theme_async: 'the block of the same name in sw-settings-storefront-index',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-settings-storefront-index'],
            blocks: {
                sw_settings_storefront_smtp_settings:
                    'sw_settings_storefront_settings_icon_cache + sw_settings_storefront_settings_speculation_rules',
            },
        }),
        ...renameTemplateBlocks({
            components: ['sw-settings-tag-detail-modal'],
            blocks: {
                sw_settings_tag_detail_modal_tabs_assignments_tab: 'sw_settings_tag_detail_modal_mt_tabs_assignments_tab',
                sw_settings_tag_detail_modal_tabs_general_tab: 'sw_settings_tag_detail_modal_mt_tabs_general_tab',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-settings-tag-detail-modal'],
            blocks: {
                sw_settings_tag_detail_modal_tabs_assignments:
                    'sw_settings_tag_detail_modal_mt_tabs and add your tab to its tagDetailModalTabs computed',
                sw_settings_tag_detail_modal_tabs_general:
                    'sw_settings_tag_detail_modal_mt_tabs and add your tab to its tagDetailModalTabs computed',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-theme-manager-detail'],
            blocks: {
                sw_theme_manager_detail_content_areas: 'sw_theme_manager_detail_content (no blocks in the mt-tabs branch)',
                sw_theme_manager_detail_content_fields: 'sw_theme_manager_detail_content (no blocks in the mt-tabs branch)',
                sw_theme_manager_detail_content_info: 'sw_theme_manager_detail_content (no blocks in the mt-tabs branch)',
                sw_theme_manager_detail_content_info_content:
                    'sw_theme_manager_detail_content (no blocks in the mt-tabs branch)',
                sw_theme_manager_detail_content_info_context_button:
                    'sw_theme_manager_detail_content (no blocks in the mt-tabs branch)',
                sw_theme_manager_detail_content_info_image:
                    'sw_theme_manager_detail_content (no blocks in the mt-tabs branch)',
                sw_theme_manager_detail_content_inheritance:
                    'sw_theme_manager_detail_content (no blocks in the mt-tabs branch)',
                sw_theme_manager_detail_content_inheritance_icon:
                    'sw_theme_manager_detail_content (no blocks in the mt-tabs branch)',
                sw_theme_manager_detail_content_inheritance_text:
                    'sw_theme_manager_detail_content (no blocks in the mt-tabs branch)',
                sw_theme_manager_detail_content_sections:
                    'sw_theme_manager_detail_content (no blocks in the mt-tabs branch)',
                sw_theme_manager_detail_context_button_option_create:
                    'sw_theme_manager_detail_content (no blocks in the mt-tabs branch)',
                sw_theme_manager_detail_context_button_option_delete:
                    'sw_theme_manager_detail_content (no blocks in the mt-tabs branch)',
                sw_theme_manager_detail_context_button_option_rename:
                    'sw_theme_manager_detail_content (no blocks in the mt-tabs branch)',
                sw_theme_manager_detail_context_button_option_reset:
                    'sw_theme_manager_detail_content (no blocks in the mt-tabs branch)',
                sw_theme_manager_detail_sidebar: 'sw_theme_manager_detail_media_modal',
                sw_theme_manager_detail_sidebar_media: 'sw_theme_manager_detail_media_modal',
                sw_theme_manager_detail_sidebar_media_items: 'sw_theme_manager_detail_media_modal',
            },
        }),
    ],
});
