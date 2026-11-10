/**
 * @sw-package framework
 */

import { templateBlockMigration, upgradeGuide } from '../helpers';
import { moveBlocks, removeBlocks } from './block-steps';

const MARKUP_CHANGED = 'The markup of the block changed, adapt the override to it.';

/** Removed blocks per component, by the block of the `mt-tabs` branch that renders the same area with other markup. */
const CHANGED_MARKUP: Record<string, Record<string, string[]>> = {
    'sw-custom-field-set-renderer': {
        sw_custom_field_set_renderer_card_mt_tabs_content: ['sw_custom_field_set_renderer_card_tabs_content'],
    },
    'sw-custom-field-translated-labels': {
        sw_custom_field_translated_labels_translated_mt_content: ['sw_custom_field_translated_labels_translated_content'],
    },
    'sw-flow-rule-modal': {
        sw_flow_rule_modal_mt_content: ['sw_flow_rule_modal_content'],
    },
    'sw-media-modal-v2': {
        sw_media_modal_v2_mt_tab_content: ['sw_media_modal_v2_tab_content'],
    },
    'sw-order-address-modal': {
        sw_order_address_modal_mt_tabs_content: ['sw_order_address_modal_tabs_content'],
    },
    'sw-order-new-customer-modal': {
        sw_order_new_customer_modal_mt_tabs_content_billing: ['sw_order_new_customer_modal_content_billing'],
        sw_order_new_customer_modal_mt_tabs_content_details: ['sw_order_new_customer_modal_content_details'],
    },
    'sw-settings-search-searchable-content': {
        sw_settings_search_searchable_content_mt_general_tab_item: [
            'sw_settings_search_searchable_content_general_tab_item',
        ],
    },
};

export default templateBlockMigration({
    id: 'template-block.legacy-sw-tabs-markup',
    deprecatedIn: '6.8.0',
    removedIn: '6.8.0',
    description:
        'The legacy sw-tabs branches of these components were removed together with blocks that have no ' +
        'counterpart in the mt-tabs branch. Move an override to the block that renders the same area now.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches'),
    ],
    usage: [
        ...moveBlocks(CHANGED_MARKUP, MARKUP_CHANGED),
        ...removeBlocks(
            'sw-bulk-edit-custom-fields',
            [
                'sw_custom_field_set_renderer_card_form_renderer_change_field',
                'sw_custom_field_set_renderer_card_form_renderer_custom_field',
            ],
            { to: 'sw_custom_field_set_renderer_card_mt_tabs_form_renderer' },
        ),
        ...removeBlocks(
            'sw-cms-el-config-product-slider',
            [
                'sw_cms_element_product_slider_config_content_products_select_result_item',
                'sw_cms_element_product_slider_config_content_products_selection_label',
            ],
            { to: 'sw_cms_element_product_slider_config_mt_content_products' },
        ),
        ...removeBlocks('sw-mail-template-index', ['sw_mail_template_list_content_card'], {
            to: 'sw_mail_template_list_router_view',
            message: 'Or override "sw-mail-template-list" and "sw-mail-header-footer-list", which the tabs render.',
        }),
        ...removeBlocks(
            'sw-theme-manager-detail',
            [
                'sw_theme_manager_detail_content_areas',
                'sw_theme_manager_detail_content_fields',
                'sw_theme_manager_detail_content_info',
                'sw_theme_manager_detail_content_info_content',
                'sw_theme_manager_detail_content_info_context_button',
                'sw_theme_manager_detail_content_info_image',
                'sw_theme_manager_detail_content_inheritance',
                'sw_theme_manager_detail_content_inheritance_icon',
                'sw_theme_manager_detail_content_inheritance_text',
                'sw_theme_manager_detail_content_sections',
                'sw_theme_manager_detail_context_button_option_create',
                'sw_theme_manager_detail_context_button_option_delete',
                'sw_theme_manager_detail_context_button_option_rename',
                'sw_theme_manager_detail_context_button_option_reset',
            ],
            {
                to: 'sw_theme_manager_detail_content',
                message: 'The mt-tabs branch of the theme manager has no blocks inside its content.',
            },
        ),
    ],
});
