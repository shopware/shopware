/**
 * @sw-package framework
 */

import { removeTemplateBlocks, renameTemplateBlocks, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sales-channel-6-8',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of the sales channel module that Shopware 6.8 renames or removes.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches'),
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks'),
    ],
    usage: [
        ...removeTemplateBlocks({
            components: ['sw-sales-channel-detail', 'sw-sales-channel-create'],
            sharedNames: ['sw_sales_channel_detail_content_tab_theme'],
            blocks: {
                sw_sales_channel_detail_content_tab_agentic_commerce_integration:
                    'sw_sales_channel_detail_content_mt_tabs and add your tab to its salesChannelDetailTabs computed',
                sw_sales_channel_detail_content_tab_analytics:
                    'sw_sales_channel_detail_content_mt_tabs and add your tab to its salesChannelDetailTabs computed',
                sw_sales_channel_detail_content_tab_export_template:
                    'sw_sales_channel_detail_content_mt_tabs and add your tab to its salesChannelDetailTabs computed',
                sw_sales_channel_detail_content_tab_general:
                    'sw_sales_channel_detail_content_mt_tabs and add your tab to its salesChannelDetailTabs computed',
                sw_sales_channel_detail_content_tab_product_comparison:
                    'sw_sales_channel_detail_content_mt_tabs and add your tab to its salesChannelDetailTabs computed',
                sw_sales_channel_detail_content_tab_product_export_insights:
                    'sw_sales_channel_detail_content_mt_tabs and add your tab to its salesChannelDetailTabs computed',
                sw_sales_channel_detail_content_tab_products:
                    'sw_sales_channel_detail_content_mt_tabs and add your tab to its salesChannelDetailTabs computed',
                sw_sales_channel_detail_content_tab_theme:
                    'sw_sales_channel_detail_content_mt_tabs and add your tab to its salesChannelDetailTabs computed',
                sw_sales_channel_detail_agentic_commerce_deprecation_banner: null,
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-sales-channel-detail-base'],
            blocks: {
                sw_sales_channel_detail_agentic_commerce_tracking: 'the SwagAgenticCommerce plugin',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-sales-channel-detail-products'],
            blocks: {
                sw_sales_channel_detail_products_card_section_primary_image:
                    'sw_sales_channel_detail_products_card_section_primary_empty_state',
                sw_sales_channel_detail_products_empty_state_image: 'sw_sales_channel_detail_products_empty_state',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-sales-channel-products-assignment-dynamic-product-groups'],
            blocks: {
                sw_sales_channel_products_assignment_dynamic_product_groups_listing_empty_icon:
                    'sw_sales_channel_products_assignment_dynamic_product_groups_listing_empty (pass the icon and texts as props of mt-empty-state)',
            },
        }),
        ...renameTemplateBlocks({
            components: ['sw-sales-channel-products-assignment-modal'],
            activeSlot: [
                'sw_sales_channel_products_assignment_modal_tab_content_categories',
                'sw_sales_channel_products_assignment_modal_tab_content_dynamic_product_groups',
                'sw_sales_channel_products_assignment_modal_tab_content_single_products',
            ],
            blocks: {
                sw_sales_channel_products_assignment_modal_tab_content_categories:
                    'sw_sales_channel_products_assignment_modal_mt_tab_content_categories',
                sw_sales_channel_products_assignment_modal_tab_content_dynamic_product_groups:
                    'sw_sales_channel_products_assignment_modal_mt_tab_content_dynamic_product_groups',
                sw_sales_channel_products_assignment_modal_tab_content_single_products:
                    'sw_sales_channel_products_assignment_modal_mt_tab_content_single_products',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-sales-channel-products-assignment-modal'],
            blocks: {
                sw_sales_channel_products_assignment_modal_tab_dynamic_product_groups:
                    'sw_sales_channel_products_assignment_modal_mt_tabs and add your tab to its productAssignmentTabs computed',
                sw_sales_channel_products_assignment_modal_tabs_categories:
                    'sw_sales_channel_products_assignment_modal_mt_tabs and add your tab to its productAssignmentTabs computed',
                sw_sales_channel_products_assignment_modal_tabs_single_products:
                    'sw_sales_channel_products_assignment_modal_mt_tabs and add your tab to its productAssignmentTabs computed',
            },
        }),
    ],
});
