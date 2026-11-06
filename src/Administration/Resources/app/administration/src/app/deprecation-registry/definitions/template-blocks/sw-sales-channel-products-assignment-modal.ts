/**
 * @sw-package framework
 */

import { removeTemplateBlock, renameTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-sales-channel-products-assignment-modal',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-sales-channel-products-assignment-modal that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches')],
    usage: [
        renameTemplateBlock({
            from: 'sw_sales_channel_products_assignment_modal_tab_content_categories',
            to: 'sw_sales_channel_products_assignment_modal_mt_tab_content_categories',
            components: ['sw-sales-channel-products-assignment-modal'],
            fix: 'unsafe-auto',
            message: 'The block no longer receives the active slot variable of sw-tabs, use activeTab instead.',
        }),
        renameTemplateBlock({
            from: 'sw_sales_channel_products_assignment_modal_tab_content_dynamic_product_groups',
            to: 'sw_sales_channel_products_assignment_modal_mt_tab_content_dynamic_product_groups',
            components: ['sw-sales-channel-products-assignment-modal'],
            fix: 'unsafe-auto',
            message: 'The block no longer receives the active slot variable of sw-tabs, use activeTab instead.',
        }),
        renameTemplateBlock({
            from: 'sw_sales_channel_products_assignment_modal_tab_content_single_products',
            to: 'sw_sales_channel_products_assignment_modal_mt_tab_content_single_products',
            components: ['sw-sales-channel-products-assignment-modal'],
            fix: 'unsafe-auto',
            message: 'The block no longer receives the active slot variable of sw-tabs, use activeTab instead.',
        }),
        removeTemplateBlock({
            from: 'sw_sales_channel_products_assignment_modal_tab_dynamic_product_groups',
            moveTo: 'sw_sales_channel_products_assignment_modal_mt_tabs and add your tab to its productAssignmentTabs computed',
            components: ['sw-sales-channel-products-assignment-modal'],
        }),
        removeTemplateBlock({
            from: 'sw_sales_channel_products_assignment_modal_tabs_categories',
            moveTo: 'sw_sales_channel_products_assignment_modal_mt_tabs and add your tab to its productAssignmentTabs computed',
            components: ['sw-sales-channel-products-assignment-modal'],
        }),
        removeTemplateBlock({
            from: 'sw_sales_channel_products_assignment_modal_tabs_single_products',
            moveTo: 'sw_sales_channel_products_assignment_modal_mt_tabs and add your tab to its productAssignmentTabs computed',
            components: ['sw-sales-channel-products-assignment-modal'],
        }),
    ],
});
