/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-sales-channel-detail',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-sales-channel-detail that Shopware 6.8 renames or removes.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches'),
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks'),
    ],
    usage: [
        removeTemplateBlock({
            from: 'sw_sales_channel_detail_content_tab_agentic_commerce_integration',
            moveTo: 'sw_sales_channel_detail_content_mt_tabs and add your tab to its salesChannelDetailTabs computed',
            components: ['sw-sales-channel-detail', 'sw-sales-channel-create'],
        }),
        removeTemplateBlock({
            from: 'sw_sales_channel_detail_content_tab_analytics',
            moveTo: 'sw_sales_channel_detail_content_mt_tabs and add your tab to its salesChannelDetailTabs computed',
            components: ['sw-sales-channel-detail', 'sw-sales-channel-create'],
        }),
        removeTemplateBlock({
            from: 'sw_sales_channel_detail_content_tab_export_template',
            moveTo: 'sw_sales_channel_detail_content_mt_tabs and add your tab to its salesChannelDetailTabs computed',
            components: ['sw-sales-channel-detail', 'sw-sales-channel-create'],
        }),
        removeTemplateBlock({
            from: 'sw_sales_channel_detail_content_tab_general',
            moveTo: 'sw_sales_channel_detail_content_mt_tabs and add your tab to its salesChannelDetailTabs computed',
            components: ['sw-sales-channel-detail', 'sw-sales-channel-create'],
        }),
        removeTemplateBlock({
            from: 'sw_sales_channel_detail_content_tab_product_comparison',
            moveTo: 'sw_sales_channel_detail_content_mt_tabs and add your tab to its salesChannelDetailTabs computed',
            components: ['sw-sales-channel-detail', 'sw-sales-channel-create'],
        }),
        removeTemplateBlock({
            from: 'sw_sales_channel_detail_content_tab_product_export_insights',
            moveTo: 'sw_sales_channel_detail_content_mt_tabs and add your tab to its salesChannelDetailTabs computed',
            components: ['sw-sales-channel-detail', 'sw-sales-channel-create'],
        }),
        removeTemplateBlock({
            from: 'sw_sales_channel_detail_content_tab_products',
            moveTo: 'sw_sales_channel_detail_content_mt_tabs and add your tab to its salesChannelDetailTabs computed',
            components: ['sw-sales-channel-detail', 'sw-sales-channel-create'],
        }),
        removeTemplateBlock({
            from: 'sw_sales_channel_detail_content_tab_theme',
            moveTo: 'sw_sales_channel_detail_content_mt_tabs and add your tab to its salesChannelDetailTabs computed',
            components: ['sw-sales-channel-detail', 'sw-sales-channel-create'],
            onlyInComponents: true,
        }),
        removeTemplateBlock({
            from: 'sw_sales_channel_detail_agentic_commerce_deprecation_banner',
            components: ['sw-sales-channel-detail', 'sw-sales-channel-create'],
        }),
    ],
});
