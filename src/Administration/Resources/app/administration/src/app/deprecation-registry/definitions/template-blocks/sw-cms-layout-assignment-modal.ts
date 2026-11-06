/**
 * @sw-package framework
 */

import { removeTemplateBlock, renameTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-cms-layout-assignment-modal',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-cms-layout-assignment-modal that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches')],
    usage: [
        renameTemplateBlock({
            from: 'sw_cms_layout_assignment_modal_category_select',
            to: 'sw_cms_layout_assignment_modal_mt_category_select',
            components: ['sw-cms-layout-assignment-modal'],
            fix: 'unsafe-auto',
            message: 'The block no longer receives the active slot variable of sw-tabs, use activeTab instead.',
        }),
        renameTemplateBlock({
            from: 'sw_cms_layout_assignment_modal_category_select_field',
            to: 'sw_cms_layout_assignment_modal_mt_category_select_field',
            components: ['sw-cms-layout-assignment-modal'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_layout_assignment_modal_landing_page_select',
            to: 'sw_cms_layout_assignment_modal_mt_landing_page_select',
            components: ['sw-cms-layout-assignment-modal'],
            fix: 'unsafe-auto',
            message: 'The block no longer receives the active slot variable of sw-tabs, use activeTab instead.',
        }),
        renameTemplateBlock({
            from: 'sw_cms_layout_assignment_modal_landing_page_select_field',
            to: 'sw_cms_layout_assignment_modal_mt_landing_page_select_field',
            components: ['sw-cms-layout-assignment-modal'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_layout_assignment_modal_product_detail_pages_column_manufacturer',
            to: 'sw_cms_layout_assignment_modal_mt_product_detail_pages_column_manufacturer',
            components: ['sw-cms-layout-assignment-modal'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_layout_assignment_modal_product_detail_pages_column_name',
            to: 'sw_cms_layout_assignment_modal_mt_product_detail_pages_column_name',
            components: ['sw-cms-layout-assignment-modal'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_layout_assignment_modal_product_detail_pages_empty_state',
            to: 'sw_cms_layout_assignment_modal_mt_product_detail_pages_empty_state',
            components: ['sw-cms-layout-assignment-modal'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_layout_assignment_modal_product_detail_pages_select',
            to: 'sw_cms_layout_assignment_modal_mt_product_detail_pages_select',
            components: ['sw-cms-layout-assignment-modal'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_layout_assignment_modal_shop_pages_select',
            to: 'sw_cms_layout_assignment_modal_mt_shop_pages_select',
            components: ['sw-cms-layout-assignment-modal'],
            fix: 'unsafe-auto',
            message: 'The block no longer receives the active slot variable of sw-tabs, use activeTab instead.',
        }),
        renameTemplateBlock({
            from: 'sw_cms_layout_assignment_modal_shop_pages_select_field',
            to: 'sw_cms_layout_assignment_modal_mt_shop_pages_select_field',
            components: ['sw-cms-layout-assignment-modal'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_layout_assignment_modal_shop_pages_select_sales_channel_field',
            to: 'sw_cms_layout_assignment_modal_mt_shop_pages_select_sales_channel_field',
            components: ['sw-cms-layout-assignment-modal'],
        }),
        renameTemplateBlock({
            from: 'sw_entity_many_to_many_assignment_card_select',
            to: 'sw_cms_layout_assignment_modal_mt_product_assignment_card_select',
            components: ['sw-cms-layout-assignment-modal'],
            onlyInComponents: true,
        }),
        removeTemplateBlock({
            from: 'sw_cms_layout_assignment_modal_tab_categories',
            moveTo: 'sw_cms_layout_assignment_modal_mt_tabs and add your tab to its layoutAssignmentTabs computed',
            components: ['sw-cms-layout-assignment-modal'],
        }),
        removeTemplateBlock({
            from: 'sw_cms_layout_assignment_modal_tab_landing_pages',
            moveTo: 'sw_cms_layout_assignment_modal_mt_tabs and add your tab to its layoutAssignmentTabs computed',
            components: ['sw-cms-layout-assignment-modal'],
        }),
        removeTemplateBlock({
            from: 'sw_cms_layout_assignment_modal_tab_shop_pages',
            moveTo: 'sw_cms_layout_assignment_modal_mt_tabs and add your tab to its layoutAssignmentTabs computed',
            components: ['sw-cms-layout-assignment-modal'],
        }),
    ],
});
