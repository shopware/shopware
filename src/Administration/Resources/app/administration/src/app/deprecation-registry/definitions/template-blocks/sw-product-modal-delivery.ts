/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-product-modal-delivery',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-product-modal-delivery that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches')],
    usage: [
        removeTemplateBlock({
            from: 'sw_product_modal_delivery_sidebar_tabs_items',
            moveTo: 'sw_product_modal_delivery_sidebar_mt_tabs and add your tab to its deliveryTabs computed',
            components: ['sw-product-modal-delivery'],
        }),
    ],
});
