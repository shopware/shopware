/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-product-detail',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-product-detail that Shopware 6.8 renames or removes.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches'),
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks'),
    ],
    usage: [
        removeTemplateBlock({
            from: 'sw_product_detail_content_tabs_additional',
            moveTo: 'sw_product_detail_content_mt_tabs and add your tab to its productDetailTabs computed',
            components: ['sw-product-detail'],
        }),
        removeTemplateBlock({
            from: 'sw_product_detail_content_tabs_advanced_prices',
            moveTo: 'sw_product_detail_content_mt_tabs and add your tab to its productDetailTabs computed',
            components: ['sw-product-detail'],
        }),
        removeTemplateBlock({
            from: 'sw_product_detail_content_tabs_advanced_variants',
            moveTo: 'sw_product_detail_content_mt_tabs and add your tab to its productDetailTabs computed',
            components: ['sw-product-detail'],
        }),
        removeTemplateBlock({
            from: 'sw_product_detail_content_tabs_cross_selling',
            moveTo: 'sw_product_detail_content_mt_tabs and add your tab to its productDetailTabs computed',
            components: ['sw-product-detail'],
        }),
        removeTemplateBlock({
            from: 'sw_product_detail_content_tabs_general',
            moveTo: 'sw_product_detail_content_mt_tabs and add your tab to its productDetailTabs computed',
            components: ['sw-product-detail'],
        }),
        removeTemplateBlock({
            from: 'sw_product_detail_content_tabs_layout',
            moveTo: 'sw_product_detail_content_mt_tabs and add your tab to its productDetailTabs computed',
            components: ['sw-product-detail'],
        }),
        removeTemplateBlock({
            from: 'sw_product_detail_content_tabs_reviews',
            moveTo: 'sw_product_detail_content_mt_tabs and add your tab to its productDetailTabs computed',
            components: ['sw-product-detail'],
        }),
        removeTemplateBlock({
            from: 'sw_product_detail_content_tabs_seo',
            moveTo: 'sw_product_detail_content_mt_tabs and add your tab to its productDetailTabs computed',
            components: ['sw-product-detail'],
        }),
        removeTemplateBlock({
            from: 'sw_product_detail_content_tabs_specifications',
            moveTo: 'sw_product_detail_content_mt_tabs and add your tab to its productDetailTabs computed',
            components: ['sw-product-detail'],
        }),
        removeTemplateBlock({ from: 'sw_product_settings_mode', components: ['sw-product-detail'], onlyInComponents: true }),
    ],
});
