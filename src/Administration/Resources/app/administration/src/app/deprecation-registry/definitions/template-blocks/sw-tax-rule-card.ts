/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-tax-rule-card',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-tax-rule-card that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks')],
    usage: [
        removeTemplateBlock({
            from: 'sw_tax_rule_card_empty_state_image',
            moveTo: 'sw_tax_rule_card_empty_state (pass the icon and texts as props of mt-empty-state)',
            components: ['sw-tax-rule-card'],
        }),
        removeTemplateBlock({
            from: 'sw_tax_rule_card_empty_state_label',
            moveTo: 'sw_tax_rule_card_empty_state (pass the icon and texts as props of mt-empty-state)',
            components: ['sw-tax-rule-card'],
        }),
    ],
});
