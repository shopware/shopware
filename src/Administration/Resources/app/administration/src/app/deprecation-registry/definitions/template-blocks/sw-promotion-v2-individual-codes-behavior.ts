/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-promotion-v2-individual-codes-behavior',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-promotion-v2-individual-codes-behavior that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks')],
    usage: [
        removeTemplateBlock({
            from: 'sw_promotion_v2_individual_codes_behavior_empty_state_icon',
            moveTo: 'sw_promotion_v2_individual_codes_behavior_empty_state (pass the icon and texts as props of mt-empty-state)',
            components: ['sw-promotion-v2-individual-codes-behavior'],
        }),
    ],
});
