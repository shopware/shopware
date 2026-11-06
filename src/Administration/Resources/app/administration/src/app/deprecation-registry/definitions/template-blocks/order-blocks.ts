/**
 * @sw-package framework
 */

import { removeTemplateBlocks, renameTemplateBlocks, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.order-6-8',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of the order module that Shopware 6.8 renames or removes.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches'),
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks'),
    ],
    usage: [
        ...renameTemplateBlocks({
            components: ['sw-order-address-modal'],
            blocks: {
                sw_order_address_modal_tabs_content_edit_address: 'sw_order_address_modal_mt_tabs_content_edit_address',
                sw_order_address_modal_tabs_content_select_address: 'sw_order_address_modal_mt_tabs_content_select_address',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-order-address-modal'],
            blocks: {
                sw_order_address_modal_tab_edit_address:
                    'sw_order_address_modal_mt_tabs and add your tab to its addressModalTabs computed',
                sw_order_address_modal_tab_select_address:
                    'sw_order_address_modal_mt_tabs and add your tab to its addressModalTabs computed',
                sw_order_address_modal_tabs:
                    'sw_order_address_modal_mt_tabs and add your tab to its addressModalTabs computed',
                sw_order_address_modal_tabs_content: 'sw_order_address_modal_mt_tabs_content (the markup changed)',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-order-create'],
            blocks: {
                sw_order_create_content_tabs_details:
                    'sw_order_create_content_mt_tabs and add your tab to its orderCreateTabs computed',
                sw_order_create_content_tabs_general:
                    'sw_order_create_content_mt_tabs and add your tab to its orderCreateTabs computed',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-order-create-address-modal'],
            blocks: {
                sw_order_create_address_modal_empty_state_content:
                    'sw_order_create_address_modal_empty_state (pass the icon and texts as props of mt-empty-state)',
            },
        }),
        ...renameTemplateBlocks({
            components: ['sw-order-create-initial-modal'],
            activeSlot: [
                'sw_order_create_modal_tabs_content',
                'sw_order_create_modal_tabs_content_customer',
                'sw_order_create_modal_tabs_content_options',
                'sw_order_create_modal_tabs_content_products',
            ],
            blocks: {
                sw_order_create_modal_tabs_content: 'sw_order_create_modal_mt_tabs_content',
                sw_order_create_modal_tabs_content_customer: 'sw_order_create_modal_mt_tabs_content_customer',
                sw_order_create_modal_tabs_content_options: 'sw_order_create_modal_mt_tabs_content_options',
                sw_order_create_modal_tabs_content_products: 'sw_order_create_modal_mt_tabs_content_products',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-order-create-initial-modal'],
            blocks: {
                sw_order_create_modal_tabs_customer:
                    'sw_order_create_modal_mt_tabs and add your tab to its orderCreateInitialModalTabs computed',
                sw_order_create_modal_tabs_extension:
                    'sw_order_create_modal_mt_tabs and add your tab to its orderCreateInitialModalTabs computed',
                sw_order_create_modal_tabs_options:
                    'sw_order_create_modal_mt_tabs and add your tab to its orderCreateInitialModalTabs computed',
                sw_order_create_modal_tabs_products:
                    'sw_order_create_modal_mt_tabs and add your tab to its orderCreateInitialModalTabs computed',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-order-customer-grid'],
            blocks: {
                sw_order_customer_grid_empty_state_icon:
                    'sw_order_customer_grid_empty_state (pass the icon and texts as props of mt-empty-state)',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-order-detail'],
            blocks: {
                sw_order_detail_content_tabs_details:
                    'sw_order_detail_content_mt_tabs and add your tab to its orderDetailTabs computed',
                sw_order_detail_content_tabs_documents:
                    'sw_order_detail_content_mt_tabs and add your tab to its orderDetailTabs computed',
                sw_order_detail_content_tabs_extension:
                    'sw_order_detail_content_mt_tabs and add your tab to its orderDetailTabs computed',
                sw_order_detail_content_tabs_general:
                    'sw_order_detail_content_mt_tabs and add your tab to its orderDetailTabs computed',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-order-detail-details'],
            blocks: {
                sw_order_detail_details_order_promotion: null,
                sw_order_detail_details_shipping_costs:
                    'sw_order_detail_general_line_items_summary_shipping_cost of sw-order-detail-general',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-order-line-items-grid-sales-channel'],
            blocks: {
                sw_order_line_items_grid_sales_channel_line_item_filter: 'sw_order_line_items_grid_sales_channel_header',
            },
        }),
        ...renameTemplateBlocks({
            components: ['sw-order-new-customer-modal'],
            activeSlot: ['sw_order_new_customer_modal_content_shipping'],
            blocks: {
                sw_order_new_customer_modal_content_billing_form: 'sw_order_new_customer_modal_mt_tabs_content_billing_form',
                sw_order_new_customer_modal_content_details_form: 'sw_order_new_customer_modal_mt_tabs_content_details_form',
                sw_order_new_customer_modal_content_details_guest:
                    'sw_order_new_customer_modal_mt_tabs_content_details_guest',
                sw_order_new_customer_modal_content_shipping: 'sw_order_new_customer_modal_mt_tabs_content_shipping',
                sw_order_new_customer_modal_content_shipping_form:
                    'sw_order_new_customer_modal_mt_tabs_content_shipping_form',
                sw_order_new_customer_modal_content_shipping_same_billing:
                    'sw_order_new_customer_modal_mt_tabs_content_shipping_same_billing',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-order-new-customer-modal'],
            blocks: {
                sw_order_new_customer_modal_content_billing:
                    'sw_order_new_customer_modal_mt_tabs_content_billing (the markup changed)',
                sw_order_new_customer_modal_content_details:
                    'sw_order_new_customer_modal_mt_tabs_content_details (the markup changed)',
                sw_order_new_customer_modal_tabs_billing:
                    'sw_order_new_customer_modal_mt_tabs and add your tab to its newCustomerModalTabs computed',
                sw_order_new_customer_modal_tabs_details:
                    'sw_order_new_customer_modal_mt_tabs and add your tab to its newCustomerModalTabs computed',
                sw_order_new_customer_modal_tabs_shipping:
                    'sw_order_new_customer_modal_mt_tabs and add your tab to its newCustomerModalTabs computed',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-order-user-card'],
            blocks: {
                sw_order_detail_base_address_modal: null,
                sw_order_detail_base_info_summary: null,
                sw_order_detail_base_info_summary_entries: null,
                sw_order_detail_base_order_overview: null,
                sw_order_detail_base_order_overview_billing_address: null,
                sw_order_detail_base_order_overview_columns: null,
                sw_order_detail_base_order_overview_email: null,
                sw_order_detail_base_order_overview_left_column: null,
                sw_order_detail_base_order_overview_left_column_slot: null,
                sw_order_detail_base_order_overview_phone_number: null,
                sw_order_detail_base_order_overview_right_column: null,
                sw_order_detail_base_order_overview_right_column_slot: null,
                sw_order_detail_base_order_overview_shipping_address: null,
                sw_order_detail_base_secondary_info_affiliate: null,
                sw_order_detail_base_secondary_info_campaign: null,
                sw_order_detail_base_secondary_info_delivery: null,
                sw_order_detail_base_secondary_info_last_change: null,
                sw_order_detail_base_secondary_info_order_language: null,
                sw_order_detail_base_secondary_info_order_overview: null,
                sw_order_detail_base_secondary_info_order_overview_contents: null,
                sw_order_detail_base_secondary_info_order_overview_left_column: null,
                sw_order_detail_base_secondary_info_order_overview_left_column_slot: null,
                sw_order_detail_base_secondary_info_order_overview_right_column: null,
                sw_order_detail_base_secondary_info_order_overview_right_column_slot: null,
                sw_order_detail_base_secondary_info_payment: null,
                sw_order_detail_base_secondary_info_sales_channel: null,
                sw_order_detail_base_secondary_info_tracking_codes: null,
                sw_order_detail_base_secondary_info_tracking_codes_display: null,
                sw_order_detail_base_secondary_info_tracking_codes_display_content: null,
                sw_order_detail_base_secondary_info_tracking_codes_display_label: null,
                sw_order_detail_base_secondary_info_tracking_codes_empty: null,
                sw_order_detail_base_secondary_info_tracking_codes_empty_content: null,
                sw_order_detail_base_secondary_info_tracking_codes_empty_label: null,
                sw_order_detail_base_secondary_info_tracking_codes_input: null,
                sw_order_detail_base_secondary_info_tracking_codes_input_content: null,
                sw_order_detail_base_secondary_info_tracking_codes_input_label: null,
                sw_order_user_card_avatar: null,
                sw_order_user_card_metadata: null,
                sw_order_user_card_metadata_container: null,
                sw_order_user_card_metadata_tags: null,
                sw_order_user_card_metadata_user_name: null,
                sw_order_user_card_row_primary: null,
                sw_order_user_card_row_secondary: null,
                sw_order_user_card_slot_additional_actions: null,
            },
        }),
    ],
});
