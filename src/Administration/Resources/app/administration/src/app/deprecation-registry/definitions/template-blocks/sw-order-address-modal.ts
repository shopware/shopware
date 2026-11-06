/**
 * @sw-package framework
 */

import { removeTemplateBlock, renameTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-order-address-modal',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-order-address-modal that Shopware 6.8 renames or removes.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches'),
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks'),
    ],
    usage: [
        renameTemplateBlock({
            from: 'sw_order_address_modal_tabs_content_edit_address',
            to: 'sw_order_address_modal_mt_tabs_content_edit_address',
            components: ['sw-order-address-modal'],
        }),
        renameTemplateBlock({
            from: 'sw_order_address_modal_tabs_content_select_address',
            to: 'sw_order_address_modal_mt_tabs_content_select_address',
            components: ['sw-order-address-modal'],
        }),
        removeTemplateBlock({
            from: 'sw_order_address_modal_tab_edit_address',
            moveTo: 'sw_order_address_modal_mt_tabs and add your tab to its addressModalTabs computed',
            components: ['sw-order-address-modal'],
        }),
        removeTemplateBlock({
            from: 'sw_order_address_modal_tab_select_address',
            moveTo: 'sw_order_address_modal_mt_tabs and add your tab to its addressModalTabs computed',
            components: ['sw-order-address-modal'],
        }),
        removeTemplateBlock({
            from: 'sw_order_address_modal_tabs',
            moveTo: 'sw_order_address_modal_mt_tabs and add your tab to its addressModalTabs computed',
            components: ['sw-order-address-modal'],
        }),
        removeTemplateBlock({
            from: 'sw_order_address_modal_tabs_content',
            moveTo: 'sw_order_address_modal_mt_tabs_content (the markup changed)',
            components: ['sw-order-address-modal'],
        }),
    ],
});
