/**
 * @sw-package framework
 */

import { templateBlockMigration, upgradeGuide } from '../helpers';
import { moveBlocks, removeBlocks } from './block-steps';

export default templateBlockMigration({
    id: 'template-block.order-detail',
    deprecatedIn: '6.8.0',
    removedIn: '6.8.0',
    description:
        'The unused sw-order-user-card was removed with its blocks. sw-order-detail-details no longer edits ' +
        'shipping costs or automatic promotions, and the sales channel line item grid lost its filter block.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-unused-order-components'),
        upgradeGuide('UPGRADE-6.8.md#removed-shipping-cost-editing-of-sw-order-detail-details'),
        upgradeGuide('UPGRADE-6.8.md#removed-the-automatic-promotion-toggle-of-orders'),
        upgradeGuide('UPGRADE-6.8.md#sw-order-line-items-grid-sales-channel'),
    ],
    usage: [
        ...moveBlocks(
            {
                'sw-order-detail-details': {
                    sw_order_detail_general_line_items_summary_shipping_cost: ['sw_order_detail_details_shipping_costs'],
                },
            },
            'The block is part of sw-order-detail-general, override that component instead.',
        ),
        ...removeBlocks('sw-order-detail-details', ['sw_order_detail_details_order_promotion'], {
            message: 'Automatic promotions can no longer be switched off in an order.',
        }),
        ...moveBlocks({
            'sw-order-line-items-grid-sales-channel': {
                sw_order_line_items_grid_sales_channel_header: ['sw_order_line_items_grid_sales_channel_line_item_filter'],
            },
        }),
        ...removeBlocks(
            'sw-order-user-card',
            [
                'sw_order_detail_base_address_modal',
                'sw_order_detail_base_info_summary',
                'sw_order_detail_base_info_summary_entries',
                'sw_order_detail_base_order_overview',
                'sw_order_detail_base_order_overview_billing_address',
                'sw_order_detail_base_order_overview_columns',
                'sw_order_detail_base_order_overview_email',
                'sw_order_detail_base_order_overview_left_column',
                'sw_order_detail_base_order_overview_left_column_slot',
                'sw_order_detail_base_order_overview_phone_number',
                'sw_order_detail_base_order_overview_right_column',
                'sw_order_detail_base_order_overview_right_column_slot',
                'sw_order_detail_base_order_overview_shipping_address',
                'sw_order_detail_base_secondary_info_affiliate',
                'sw_order_detail_base_secondary_info_campaign',
                'sw_order_detail_base_secondary_info_delivery',
                'sw_order_detail_base_secondary_info_last_change',
                'sw_order_detail_base_secondary_info_order_language',
                'sw_order_detail_base_secondary_info_order_overview',
                'sw_order_detail_base_secondary_info_order_overview_contents',
                'sw_order_detail_base_secondary_info_order_overview_left_column',
                'sw_order_detail_base_secondary_info_order_overview_left_column_slot',
                'sw_order_detail_base_secondary_info_order_overview_right_column',
                'sw_order_detail_base_secondary_info_order_overview_right_column_slot',
                'sw_order_detail_base_secondary_info_payment',
                'sw_order_detail_base_secondary_info_sales_channel',
                'sw_order_detail_base_secondary_info_tracking_codes',
                'sw_order_detail_base_secondary_info_tracking_codes_display',
                'sw_order_detail_base_secondary_info_tracking_codes_display_content',
                'sw_order_detail_base_secondary_info_tracking_codes_display_label',
                'sw_order_detail_base_secondary_info_tracking_codes_empty',
                'sw_order_detail_base_secondary_info_tracking_codes_empty_content',
                'sw_order_detail_base_secondary_info_tracking_codes_empty_label',
                'sw_order_detail_base_secondary_info_tracking_codes_input',
                'sw_order_detail_base_secondary_info_tracking_codes_input_content',
                'sw_order_detail_base_secondary_info_tracking_codes_input_label',
                'sw_order_user_card_avatar',
                'sw_order_user_card_metadata',
                'sw_order_user_card_metadata_container',
                'sw_order_user_card_metadata_tags',
                'sw_order_user_card_metadata_user_name',
                'sw_order_user_card_row_primary',
                'sw_order_user_card_row_secondary',
                'sw_order_user_card_slot_additional_actions',
            ],
            { message: 'sw-order-user-card was removed without replacement.' },
        ),
    ],
});
