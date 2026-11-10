/**
 * @sw-package framework
 */

import { templateBlockMigration, upgradeGuide } from '../helpers';
import { moveBlocks, removeBlocks } from './block-steps';

export default templateBlockMigration({
    id: 'template-block.removed-admin-blocks',
    deprecatedIn: '6.8.0',
    removedIn: '6.8.0',
    description: 'Single Twig blocks of several Administration pages were removed or merged into a surrounding block.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#sw-theme-manager-detail'),
        upgradeGuide('UPGRADE-6.8.md#sw-settings-customer-group-detail'),
        upgradeGuide('UPGRADE-6.8.md#sw-integration-list'),
        upgradeGuide('UPGRADE-6.8.md#sw-manufacturer-detail'),
        upgradeGuide('UPGRADE-6.8.md#sw-product-stream-filter'),
        upgradeGuide('UPGRADE-6.8.md#sw-product-detail'),
        upgradeGuide('UPGRADE-6.8.md#removed-the-product-detail-advanced-mode'),
        upgradeGuide('UPGRADE-6.8.md#sw-settings-mailer'),
        upgradeGuide('UPGRADE-6.8.md#deprecated-password-verification-members-in-sw-users-permissions-user-listing'),
    ],
    usage: [
        ...moveBlocks({
            'sw-theme-manager-detail': {
                sw_theme_manager_detail_media_modal: [
                    'sw_theme_manager_detail_sidebar',
                    'sw_theme_manager_detail_sidebar_media',
                    'sw_theme_manager_detail_sidebar_media_items',
                ],
            },
            'sw-settings-customer-group-detail': {
                sw_settings_customer_group_detail_content_card_tax_display: [
                    'sw_settings_customer_group_detail_content_card_display_gross',
                ],
            },
            'sw-product-stream-filter': {
                sw_condition_base: ['sw_product_stream_filter'],
                sw_condition_base_content: ['sw_product_stream_filter_container'],
            },
            'sw-settings-mailer': {
                sw_settings_mailer_agent_options: ['sw_settings_mailer_headline_agent'],
            },
        }),
        ...removeBlocks('sw-integration-list', ['sw_integration_list_introduction']),
        ...removeBlocks('sw-manufacturer-detail', ['sw_manufacturer_detail_sidebar']),
        ...removeBlocks('sw-product-detail', ['sw_product_settings_mode'], {
            message: 'The advanced mode of the product detail page was removed.',
        }),
        ...removeBlocks('sw-users-permissions-user-listing', ['sw_settings_user_list_delete_modal_input__confirm_password']),
    ],
});
