/**
 * @sw-package framework
 */

import { renameSnippetKey, snippetKeyMigration, upgradeGuide } from '../helpers';

// The labels `cartTaxDisplay.label`, `lineItemOfTypeRule.label` and `dayOfWeekRule` stay, only the option keys move.
export default snippetKeyMigration({
    id: 'snippet.condition-removed-keys',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The option snippets of the tax display, line item type and promotion code type rule conditions are removed. Use the snippets of sw-condition-generic instead.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removal-of-snippets'),
    ],
    usage: [
        renameSnippetKey({
            from: 'global.sw-condition.condition.cartTaxDisplay.gross',
            to: 'global.sw-condition-generic.cartTaxDisplay.taxDisplay.options.gross',
        }),
        renameSnippetKey({
            from: 'global.sw-condition.condition.cartTaxDisplay.net',
            to: 'global.sw-condition-generic.cartTaxDisplay.taxDisplay.options.net',
        }),
        renameSnippetKey({
            from: 'global.sw-condition.condition.lineItemOfTypeRule.product',
            to: 'global.sw-condition-generic.cartLineItemOfType.lineItemType.options.product',
        }),
        renameSnippetKey({
            from: 'global.sw-condition.condition.lineItemOfTypeRule.discount_surcharge',
            to: 'global.sw-condition-generic.cartLineItemOfType.lineItemType.options.promotion',
        }),
        renameSnippetKey({
            from: 'global.sw-condition.condition.promotionCodeOfTypeRule.global',
            to: 'global.sw-condition-generic.promotionCodeOfType.promotionCodeType.options.global',
        }),
        renameSnippetKey({
            from: 'global.sw-condition.condition.promotionCodeOfTypeRule.fixed',
            to: 'global.sw-condition-generic.promotionCodeOfType.promotionCodeType.options.fixed',
        }),
        renameSnippetKey({
            from: 'global.sw-condition.condition.promotionCodeOfTypeRule.individual',
            to: 'global.sw-condition-generic.promotionCodeOfType.promotionCodeType.options.individual',
        }),
    ],
});
