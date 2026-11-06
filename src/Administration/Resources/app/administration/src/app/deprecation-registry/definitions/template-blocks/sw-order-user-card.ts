/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-order-user-card',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-order-user-card that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks')],
    usage: [
        removeTemplateBlock({ from: 'sw_order_detail_base_address_modal', components: ['sw-order-user-card'] }),
        removeTemplateBlock({ from: 'sw_order_detail_base_info_summary', components: ['sw-order-user-card'] }),
        removeTemplateBlock({ from: 'sw_order_detail_base_info_summary_entries', components: ['sw-order-user-card'] }),
        removeTemplateBlock({ from: 'sw_order_detail_base_order_overview', components: ['sw-order-user-card'] }),
        removeTemplateBlock({
            from: 'sw_order_detail_base_order_overview_billing_address',
            components: ['sw-order-user-card'],
        }),
        removeTemplateBlock({ from: 'sw_order_detail_base_order_overview_columns', components: ['sw-order-user-card'] }),
        removeTemplateBlock({ from: 'sw_order_detail_base_order_overview_email', components: ['sw-order-user-card'] }),
        removeTemplateBlock({ from: 'sw_order_detail_base_order_overview_left_column', components: ['sw-order-user-card'] }),
        removeTemplateBlock({
            from: 'sw_order_detail_base_order_overview_left_column_slot',
            components: ['sw-order-user-card'],
        }),
        removeTemplateBlock({
            from: 'sw_order_detail_base_order_overview_phone_number',
            components: ['sw-order-user-card'],
        }),
        removeTemplateBlock({
            from: 'sw_order_detail_base_order_overview_right_column',
            components: ['sw-order-user-card'],
        }),
        removeTemplateBlock({
            from: 'sw_order_detail_base_order_overview_right_column_slot',
            components: ['sw-order-user-card'],
        }),
        removeTemplateBlock({
            from: 'sw_order_detail_base_order_overview_shipping_address',
            components: ['sw-order-user-card'],
        }),
        removeTemplateBlock({ from: 'sw_order_detail_base_secondary_info_affiliate', components: ['sw-order-user-card'] }),
        removeTemplateBlock({ from: 'sw_order_detail_base_secondary_info_campaign', components: ['sw-order-user-card'] }),
        removeTemplateBlock({ from: 'sw_order_detail_base_secondary_info_delivery', components: ['sw-order-user-card'] }),
        removeTemplateBlock({ from: 'sw_order_detail_base_secondary_info_last_change', components: ['sw-order-user-card'] }),
        removeTemplateBlock({
            from: 'sw_order_detail_base_secondary_info_order_language',
            components: ['sw-order-user-card'],
        }),
        removeTemplateBlock({
            from: 'sw_order_detail_base_secondary_info_order_overview',
            components: ['sw-order-user-card'],
        }),
        removeTemplateBlock({
            from: 'sw_order_detail_base_secondary_info_order_overview_contents',
            components: ['sw-order-user-card'],
        }),
        removeTemplateBlock({
            from: 'sw_order_detail_base_secondary_info_order_overview_left_column',
            components: ['sw-order-user-card'],
        }),
        removeTemplateBlock({
            from: 'sw_order_detail_base_secondary_info_order_overview_left_column_slot',
            components: ['sw-order-user-card'],
        }),
        removeTemplateBlock({
            from: 'sw_order_detail_base_secondary_info_order_overview_right_column',
            components: ['sw-order-user-card'],
        }),
        removeTemplateBlock({
            from: 'sw_order_detail_base_secondary_info_order_overview_right_column_slot',
            components: ['sw-order-user-card'],
        }),
        removeTemplateBlock({ from: 'sw_order_detail_base_secondary_info_payment', components: ['sw-order-user-card'] }),
        removeTemplateBlock({
            from: 'sw_order_detail_base_secondary_info_sales_channel',
            components: ['sw-order-user-card'],
        }),
        removeTemplateBlock({
            from: 'sw_order_detail_base_secondary_info_tracking_codes',
            components: ['sw-order-user-card'],
        }),
        removeTemplateBlock({
            from: 'sw_order_detail_base_secondary_info_tracking_codes_display',
            components: ['sw-order-user-card'],
        }),
        removeTemplateBlock({
            from: 'sw_order_detail_base_secondary_info_tracking_codes_display_content',
            components: ['sw-order-user-card'],
        }),
        removeTemplateBlock({
            from: 'sw_order_detail_base_secondary_info_tracking_codes_display_label',
            components: ['sw-order-user-card'],
        }),
        removeTemplateBlock({
            from: 'sw_order_detail_base_secondary_info_tracking_codes_empty',
            components: ['sw-order-user-card'],
        }),
        removeTemplateBlock({
            from: 'sw_order_detail_base_secondary_info_tracking_codes_empty_content',
            components: ['sw-order-user-card'],
        }),
        removeTemplateBlock({
            from: 'sw_order_detail_base_secondary_info_tracking_codes_empty_label',
            components: ['sw-order-user-card'],
        }),
        removeTemplateBlock({
            from: 'sw_order_detail_base_secondary_info_tracking_codes_input',
            components: ['sw-order-user-card'],
        }),
        removeTemplateBlock({
            from: 'sw_order_detail_base_secondary_info_tracking_codes_input_content',
            components: ['sw-order-user-card'],
        }),
        removeTemplateBlock({
            from: 'sw_order_detail_base_secondary_info_tracking_codes_input_label',
            components: ['sw-order-user-card'],
        }),
        removeTemplateBlock({ from: 'sw_order_user_card_avatar', components: ['sw-order-user-card'] }),
        removeTemplateBlock({ from: 'sw_order_user_card_metadata', components: ['sw-order-user-card'] }),
        removeTemplateBlock({ from: 'sw_order_user_card_metadata_container', components: ['sw-order-user-card'] }),
        removeTemplateBlock({ from: 'sw_order_user_card_metadata_tags', components: ['sw-order-user-card'] }),
        removeTemplateBlock({ from: 'sw_order_user_card_metadata_user_name', components: ['sw-order-user-card'] }),
        removeTemplateBlock({ from: 'sw_order_user_card_row_primary', components: ['sw-order-user-card'] }),
        removeTemplateBlock({ from: 'sw_order_user_card_row_secondary', components: ['sw-order-user-card'] }),
        removeTemplateBlock({ from: 'sw_order_user_card_slot_additional_actions', components: ['sw-order-user-card'] }),
    ],
});
