/**
 * @sw-package framework
 */

import { removeTemplateBlocks, renameTemplateBlocks, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.misc-6-8',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of other Administration components that Shopware 6.8 renames or removes.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches'),
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks'),
    ],
    usage: [
        ...removeTemplateBlocks({
            components: ['sw-bulk-edit-custom-fields'],
            blocks: {
                sw_custom_field_set_renderer_card_form_renderer_change_field:
                    'sw_custom_field_set_renderer_card_mt_tabs_form_renderer',
                sw_custom_field_set_renderer_card_form_renderer_custom_field:
                    'sw_custom_field_set_renderer_card_mt_tabs_form_renderer',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-category-view'],
            blocks: {
                sw_category_view_tabs_cms: 'sw_category_view_mt_tabs and add your tab to its categoryViewTabs computed',
                sw_category_view_tabs_general: 'sw_category_view_mt_tabs and add your tab to its categoryViewTabs computed',
                sw_category_view_tabs_products: 'sw_category_view_mt_tabs and add your tab to its categoryViewTabs computed',
                sw_category_view_tabs_seo: 'sw_category_view_mt_tabs and add your tab to its categoryViewTabs computed',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-condition-line-item-in-category'],
            blocks: {
                sw_condition_line_item_in_category_field_operator: 'sw-condition-generic',
                sw_condition_line_item_in_category_field_identifiers: 'sw-condition-generic',
                sw_condition_line_item_in_category_field_result_item: 'sw-condition-generic',
                sw_condition_line_item_in_category_field_result_item_content: 'sw-condition-generic',
                sw_condition_line_item_in_category_field_description: 'sw-condition-generic',
                sw_condition_line_item_in_category_field_description_default: 'sw-condition-generic',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-condition-line-item-purchase-price'],
            blocks: {
                sw_condition_line_item_purchase_price_field_gross_net: 'sw-condition-generic',
                sw_condition_line_item_purchase_price_field_operator: 'sw-condition-generic',
                sw_condition_line_item_purchase_price_field_amount: 'sw-condition-generic',
            },
        }),
        ...renameTemplateBlocks({
            components: ['sw-custom-field-set-renderer', 'sw-bulk-edit-custom-fields'],
            sharedNames: ['sw_custom_field_set_renderer_card_form_renderer'],
            blocks: {
                sw_custom_field_set_renderer_card_form_renderer: 'sw_custom_field_set_renderer_card_mt_tabs_form_renderer',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-custom-field-set-renderer', 'sw-bulk-edit-custom-fields'],
            blocks: {
                sw_custom_field_set_renderer_card_tabs:
                    'sw_custom_field_set_renderer_card_mt_tabs and add your tab to its customFieldSetTabs computed',
                sw_custom_field_set_renderer_card_tabs_content:
                    'sw_custom_field_set_renderer_card_mt_tabs_content (the markup changed)',
            },
        }),
        ...renameTemplateBlocks({
            components: ['sw-custom-field-translated-labels'],
            blocks: {
                sw_custom_field_translated_labels_translated_content_field:
                    'sw_custom_field_translated_labels_translated_mt_content_field',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-custom-field-translated-labels'],
            blocks: {
                sw_custom_field_translated_labels_translated_content:
                    'sw_custom_field_translated_labels_translated_mt_content (the markup changed)',
                sw_custom_field_translated_labels_translated_tabs:
                    'sw_custom_field_translated_labels_translated_mt_tabs and add your tab to its translatedLabelTabs computed',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-customer-detail'],
            blocks: {
                sw_customer_detail_content_tab_addresses:
                    'sw_customer_detail_content_mt_tabs and add your tab to its customerDetailTabs computed',
                sw_customer_detail_content_tab_after:
                    'sw_customer_detail_content_mt_tabs and add your tab to its customerDetailTabs computed',
                sw_customer_detail_content_tab_general:
                    'sw_customer_detail_content_mt_tabs and add your tab to its customerDetailTabs computed',
                sw_customer_detail_content_tab_order:
                    'sw_customer_detail_content_mt_tabs and add your tab to its customerDetailTabs computed',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-landing-page-view'],
            blocks: {
                sw_landing_page_view_tabs_cms:
                    'sw_landing_page_view_mt_tabs and add your tab to its landingPageViewTabs computed',
                sw_landing_page_view_tabs_general:
                    'sw_landing_page_view_mt_tabs and add your tab to its landingPageViewTabs computed',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-mail-header-footer-list'],
            blocks: {
                sw_mail_header_footer_list_grid_empty_state_icon:
                    'sw_mail_header_footer_list_grid_empty_state (pass the icon and texts as props of mt-empty-state)',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-popover'],
            blocks: {
                sw_popover_deprecated: 'mt-floating-ui',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-profile-index'],
            blocks: {
                sw_profile_index_tabs_item_general: 'sw_profile_index_mt_tabs and add your tab to its profileTabs computed',
                sw_profile_index_tabs_item_search_preferences:
                    'sw_profile_index_mt_tabs and add your tab to its profileTabs computed',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-tax-rule-card'],
            blocks: {
                sw_tax_rule_card_empty_state_image:
                    'sw_tax_rule_card_empty_state (pass the icon and texts as props of mt-empty-state)',
                sw_tax_rule_card_empty_state_label:
                    'sw_tax_rule_card_empty_state (pass the icon and texts as props of mt-empty-state)',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-users-permissions-user-listing'],
            blocks: {
                sw_settings_user_list_delete_modal_input__confirm_password: null,
            },
        }),
    ],
});
