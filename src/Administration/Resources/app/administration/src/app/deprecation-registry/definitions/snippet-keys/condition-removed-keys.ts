import { reference, removeSnippetKey, snippetKeyMigration } from '../helpers';

export default snippetKeyMigration({
    id: 'snippet.condition-removed-keys',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'Several condition snippet keys are deprecated because the corresponding rule condition labels were removed.',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.7.md#administration' }),
    ],
    usage: [
        removeSnippetKey({ from: 'global.sw-condition.condition.cartTaxDisplay', fix: 'manual' }),
        removeSnippetKey({ from: 'global.sw-condition.condition.lineItemOfTypeRule', fix: 'manual' }),
        removeSnippetKey({ from: 'global.sw-condition.condition.promotionCodeOfTypeRule', fix: 'manual' }),
        removeSnippetKey({ from: 'global.sw-condition.condition.dayOfWeekRule', fix: 'manual' }),
    ],
});
