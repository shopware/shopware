/**
 * @sw-package framework
 */

import { templateBlockMigration, upgradeGuide } from '../helpers';
import { removeBlocks } from './block-steps';

export default templateBlockMigration({
    id: 'template-block.promotion-empty-state-hero',
    deprecatedIn: '6.8.0',
    removedIn: '6.8.0',
    description: 'sw-promotion-v2-empty-state-hero was removed without replacement, together with all of its blocks.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#sw-promotion-v2-empty-state-hero'),
    ],
    usage: removeBlocks('sw-promotion-v2-empty-state-hero', [
        'sw_promotion_v2_empty_state_hero',
        'sw_promotion_v2_empty_state_hero_content',
        'sw_promotion_v2_empty_state_hero_content_actions',
        'sw_promotion_v2_empty_state_hero_content_description',
        'sw_promotion_v2_empty_state_hero_content_title',
        'sw_promotion_v2_empty_state_hero_image',
        'sw_promotion_v2_empty_state_hero_image_asset',
        'sw_promotion_v2_empty_state_hero_slot_actions',
    ]),
});
