/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-promotion-v2-detail',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-promotion-v2-detail that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches')],
    usage: [
        removeTemplateBlock({
            from: 'sw_promotion_v2_detail_content_tabs_conditions',
            moveTo: 'sw_promotion_v2_detail_content_mt_tabs and add your tab to its promotionDetailTabs computed',
            components: ['sw-promotion-v2-detail'],
        }),
        removeTemplateBlock({
            from: 'sw_promotion_v2_detail_content_tabs_discounts',
            moveTo: 'sw_promotion_v2_detail_content_mt_tabs and add your tab to its promotionDetailTabs computed',
            components: ['sw-promotion-v2-detail'],
        }),
        removeTemplateBlock({
            from: 'sw_promotion_v2_detail_content_tabs_general',
            moveTo: 'sw_promotion_v2_detail_content_mt_tabs and add your tab to its promotionDetailTabs computed',
            components: ['sw-promotion-v2-detail'],
        }),
    ],
});
