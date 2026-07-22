/**
 * @sw-package framework
 */

import { collectExpressionReferences } from './expression-references';

describe('build/vue-setup-transform/template-analyzer/expression-references', () => {
    it.each([
        [
            'plain identifier read',
            'info',
            [],
            ['info'],
        ],
        [
            'member access reads the object, not the property',
            'source.headline',
            [],
            ['source'],
        ],
        [
            'optional chain with computed key reads object and key',
            'source?.[dynamicKey]',
            [],
            ['source', 'dynamicKey'],
        ],
        [
            'TS as-cast is transparent',
            '(maybeInfo as string | undefined)?.toUpperCase()',
            [],
            ['maybeInfo'],
        ],
        [
            'callback parameters shadow outer names',
            'items.map(({ info, label: localLabel }) => info + localLabel).join(",")',
            [],
            ['items'],
        ],
        [
            'callback parameter defaults are reads',
            'items.map(({ label = fallbackLabel }) => label)',
            [],
            ['items', 'fallbackLabel'],
        ],
        [
            'earlier parameters shadow reads in later defaults',
            '((first, { second = first }) => second)(source)',
            [],
            ['source'],
        ],
        [
            'static object keys are not reads',
            '({ info: value })',
            [],
            ['value'],
        ],
        [
            'template-scope names are excluded',
            'info + label',
            ['info'],
            ['label'],
        ],
    ])('%s', (_name, expression, templateScope, expected) => {
        const references = collectExpressionReferences(expression, new Set(templateScope as string[]));

        expect(Array.from(references).sort()).toEqual([...(expected as string[])].sort());
    });
});
