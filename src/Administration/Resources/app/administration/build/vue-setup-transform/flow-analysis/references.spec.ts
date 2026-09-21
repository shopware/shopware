/**
 * @sw-package framework
 */

import { collectExpressionOccurrences, collectExpressionReferences, collectPatternOccurrences } from './references';

function getReferences(expression: string, templateScope: string[] = []): string[] {
    return Array.from(collectExpressionReferences(expression, new Set(templateScope))).sort();
}

/** Every occurrence as `name@start-end[:expansion]`, so a spec can pin the exact rewrite sites. */
function getOccurrences(expression: string, templateScope: string[] = []): string[] {
    return collectExpressionOccurrences(expression, new Set(templateScope)).map(
        (occurrence) =>
            `${occurrence.name}@${occurrence.start}-${occurrence.end}` +
            (occurrence.expansion === 'plain' ? '' : `:${occurrence.expansion}`),
    );
}

describe('build/vue-setup-transform/flow-analysis references', () => {
    it('reads a plain identifier', () => {
        expect(getReferences('info')).toEqual(['info']);
    });

    it('reads the object of a member access, not the property', () => {
        expect(getReferences('source.headline')).toEqual(['source']);
    });

    it('reads both the object and the key of a computed optional chain', () => {
        expect(getReferences('source?.[dynamicKey]')).toEqual([
            'dynamicKey',
            'source',
        ]);
    });

    it('looks through TS as-casts', () => {
        expect(getReferences('(maybeInfo as string | undefined)?.toUpperCase()')).toEqual(['maybeInfo']);
    });

    it('lets callback parameters shadow outer names', () => {
        expect(getReferences('items.map(({ info, label: localLabel }) => info + localLabel).join(",")')).toEqual([
            'items',
        ]);
    });

    it('does not let a named function-expression id suppress a same-named sibling read', () => {
        // The first `helper` is the expression's own name (scoped to its body); the second is a real
        // outer read that must still be reported.
        expect(getReferences('[function helper() {}, helper][1]()')).toEqual(['helper']);
    });

    it('does not let a named class-expression id suppress a same-named sibling read', () => {
        expect(getReferences('[class Holder {}, Holder][1]')).toEqual(['Holder']);
    });

    it('reads default values inside callback parameters', () => {
        expect(getReferences('items.map(({ label = fallbackLabel }) => label)')).toEqual([
            'fallbackLabel',
            'items',
        ]);
    });

    it('lets earlier parameters shadow reads in later defaults', () => {
        expect(getReferences('((first, { second = first }) => second)(source)')).toEqual(['source']);
    });

    it('does not read static object keys', () => {
        expect(getReferences('({ info: value })')).toEqual(['value']);
    });

    it('excludes template-scope names', () => {
        expect(
            getReferences('info + label', [
                'info',
            ]),
        ).toEqual(['label']);
    });

    it('handles inline-handler statements, scoping local declarations', () => {
        // Not a single expression, so it parses as statements: `doubled` is declared locally and does
        // not read from setup, while `count` and `emit` do.
        expect(getReferences('const doubled = count * 2; emit(doubled)')).toEqual([
            'count',
            'emit',
        ]);
    });

    it('scopes block-statement declarations', () => {
        expect(getReferences('if (visible) { const local = count; log(local) }')).toEqual([
            'count',
            'log',
            'visible',
        ]);
    });

    it('scopes catch-clause parameters', () => {
        expect(getReferences('try { risky() } catch (error) { report(error) }')).toEqual([
            'report',
            'risky',
        ]);
    });

    it('scopes a named function expression and its parameters', () => {
        // `helper` and `value` are locally declared; `factor` is read from setup scope.
        expect(getReferences('[1].map(function helper(value) { return value * factor; })')).toEqual(['factor']);
    });

    describe('occurrences', () => {
        it('reports the range of every read, in source order', () => {
            expect(getOccurrences('count + count * factor')).toEqual([
                'count@0-5',
                'count@8-13',
                'factor@16-22',
            ]);
        });

        it('reports a write target like any other read, so a rewrite covers it', () => {
            // `count++` has to be rewritten as much as `{{ count }}` does - it is the write that made the
            // destructured slot prop a trap in the first place.
            expect(getOccurrences('count++')).toEqual(['count@0-5']);
        });

        it('marks a shorthand object property so the key survives the rewrite', () => {
            expect(getOccurrences('({ info, label: caption })')).toEqual([
                'info@3-7:shorthand-property',
                'caption@16-23',
            ]);
        });

        it('reports no occurrence for a shadowed or template-scoped name', () => {
            expect(
                getOccurrences('items.map((info) => info + label)', [
                    'items',
                ]),
            ).toEqual(['label@27-32']);
        });

        it('reports binding-pattern reads relative to the pattern text', () => {
            expect(
                collectPatternOccurrences('{ label = fallbackLabel, [key]: value }', new Set()).map(
                    (occurrence) => `${occurrence.name}@${occurrence.start}-${occurrence.end}`,
                ),
            ).toEqual([
                'fallbackLabel@10-23',
                'key@26-29',
            ]);
        });

        it('reports nothing for an unparseable binding pattern', () => {
            expect(collectPatternOccurrences('{ not a pattern', new Set())).toEqual([]);
        });
    });
});
