/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-promotion-v2-empty-state-hero',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-promotion-v2-empty-state-hero that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks')],
    usage: [
        removeTemplateBlock({ from: 'sw_promotion_v2_empty_state_hero', components: ['sw-promotion-v2-empty-state-hero'] }),
        removeTemplateBlock({
            from: 'sw_promotion_v2_empty_state_hero_content',
            components: ['sw-promotion-v2-empty-state-hero'],
        }),
        removeTemplateBlock({
            from: 'sw_promotion_v2_empty_state_hero_content_actions',
            components: ['sw-promotion-v2-empty-state-hero'],
        }),
        removeTemplateBlock({
            from: 'sw_promotion_v2_empty_state_hero_content_description',
            components: ['sw-promotion-v2-empty-state-hero'],
        }),
        removeTemplateBlock({
            from: 'sw_promotion_v2_empty_state_hero_content_title',
            components: ['sw-promotion-v2-empty-state-hero'],
        }),
        removeTemplateBlock({
            from: 'sw_promotion_v2_empty_state_hero_image',
            components: ['sw-promotion-v2-empty-state-hero'],
        }),
        removeTemplateBlock({
            from: 'sw_promotion_v2_empty_state_hero_image_asset',
            components: ['sw-promotion-v2-empty-state-hero'],
        }),
        removeTemplateBlock({
            from: 'sw_promotion_v2_empty_state_hero_slot_actions',
            components: ['sw-promotion-v2-empty-state-hero'],
        }),
    ],
});
