/**
 * @sw-package framework
 */

import { removeTemplateBlock, renameTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-order-create-initial-modal',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-order-create-initial-modal that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches')],
    usage: [
        renameTemplateBlock({
            from: 'sw_order_create_modal_tabs_content',
            to: 'sw_order_create_modal_mt_tabs_content',
            components: ['sw-order-create-initial-modal'],
            fix: 'unsafe-auto',
            message: 'The block no longer receives the active slot variable of sw-tabs, use activeTab instead.',
        }),
        renameTemplateBlock({
            from: 'sw_order_create_modal_tabs_content_customer',
            to: 'sw_order_create_modal_mt_tabs_content_customer',
            components: ['sw-order-create-initial-modal'],
            fix: 'unsafe-auto',
            message: 'The block no longer receives the active slot variable of sw-tabs, use activeTab instead.',
        }),
        renameTemplateBlock({
            from: 'sw_order_create_modal_tabs_content_options',
            to: 'sw_order_create_modal_mt_tabs_content_options',
            components: ['sw-order-create-initial-modal'],
            fix: 'unsafe-auto',
            message: 'The block no longer receives the active slot variable of sw-tabs, use activeTab instead.',
        }),
        renameTemplateBlock({
            from: 'sw_order_create_modal_tabs_content_products',
            to: 'sw_order_create_modal_mt_tabs_content_products',
            components: ['sw-order-create-initial-modal'],
            fix: 'unsafe-auto',
            message: 'The block no longer receives the active slot variable of sw-tabs, use activeTab instead.',
        }),
        removeTemplateBlock({
            from: 'sw_order_create_modal_tabs_customer',
            moveTo: 'sw_order_create_modal_mt_tabs and add your tab to its orderCreateInitialModalTabs computed',
            components: ['sw-order-create-initial-modal'],
        }),
        removeTemplateBlock({
            from: 'sw_order_create_modal_tabs_extension',
            moveTo: 'sw_order_create_modal_mt_tabs and add your tab to its orderCreateInitialModalTabs computed',
            components: ['sw-order-create-initial-modal'],
        }),
        removeTemplateBlock({
            from: 'sw_order_create_modal_tabs_options',
            moveTo: 'sw_order_create_modal_mt_tabs and add your tab to its orderCreateInitialModalTabs computed',
            components: ['sw-order-create-initial-modal'],
        }),
        removeTemplateBlock({
            from: 'sw_order_create_modal_tabs_products',
            moveTo: 'sw_order_create_modal_mt_tabs and add your tab to its orderCreateInitialModalTabs computed',
            components: ['sw-order-create-initial-modal'],
        }),
    ],
});
