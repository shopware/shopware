/**
 * @sw-package framework
 */

import { removeTemplateBlock, renameTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-order-new-customer-modal',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-order-new-customer-modal that Shopware 6.8 renames or removes.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches'),
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks'),
    ],
    usage: [
        renameTemplateBlock({
            from: 'sw_order_new_customer_modal_content_billing_form',
            to: 'sw_order_new_customer_modal_mt_tabs_content_billing_form',
            components: ['sw-order-new-customer-modal'],
        }),
        renameTemplateBlock({
            from: 'sw_order_new_customer_modal_content_details_form',
            to: 'sw_order_new_customer_modal_mt_tabs_content_details_form',
            components: ['sw-order-new-customer-modal'],
        }),
        renameTemplateBlock({
            from: 'sw_order_new_customer_modal_content_details_guest',
            to: 'sw_order_new_customer_modal_mt_tabs_content_details_guest',
            components: ['sw-order-new-customer-modal'],
        }),
        renameTemplateBlock({
            from: 'sw_order_new_customer_modal_content_shipping',
            to: 'sw_order_new_customer_modal_mt_tabs_content_shipping',
            components: ['sw-order-new-customer-modal'],
            fix: 'unsafe-auto',
            message: 'The block no longer receives the active slot variable of sw-tabs, use activeTab instead.',
        }),
        renameTemplateBlock({
            from: 'sw_order_new_customer_modal_content_shipping_form',
            to: 'sw_order_new_customer_modal_mt_tabs_content_shipping_form',
            components: ['sw-order-new-customer-modal'],
        }),
        renameTemplateBlock({
            from: 'sw_order_new_customer_modal_content_shipping_same_billing',
            to: 'sw_order_new_customer_modal_mt_tabs_content_shipping_same_billing',
            components: ['sw-order-new-customer-modal'],
        }),
        removeTemplateBlock({
            from: 'sw_order_new_customer_modal_content_billing',
            moveTo: 'sw_order_new_customer_modal_mt_tabs_content_billing (the markup changed)',
            components: ['sw-order-new-customer-modal'],
        }),
        removeTemplateBlock({
            from: 'sw_order_new_customer_modal_content_details',
            moveTo: 'sw_order_new_customer_modal_mt_tabs_content_details (the markup changed)',
            components: ['sw-order-new-customer-modal'],
        }),
        removeTemplateBlock({
            from: 'sw_order_new_customer_modal_tabs_billing',
            moveTo: 'sw_order_new_customer_modal_mt_tabs and add your tab to its newCustomerModalTabs computed',
            components: ['sw-order-new-customer-modal'],
        }),
        removeTemplateBlock({
            from: 'sw_order_new_customer_modal_tabs_details',
            moveTo: 'sw_order_new_customer_modal_mt_tabs and add your tab to its newCustomerModalTabs computed',
            components: ['sw-order-new-customer-modal'],
        }),
        removeTemplateBlock({
            from: 'sw_order_new_customer_modal_tabs_shipping',
            moveTo: 'sw_order_new_customer_modal_mt_tabs and add your tab to its newCustomerModalTabs computed',
            components: ['sw-order-new-customer-modal'],
        }),
    ],
});
