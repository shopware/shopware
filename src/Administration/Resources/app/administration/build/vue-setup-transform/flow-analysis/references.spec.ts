/**
 * @sw-package framework
 */

import { collectExpressionReferences, collectExpressionWriteTargets } from './references';

function getReferences(expression: string, templateScope: string[] = []): string[] {
    return Array.from(collectExpressionReferences(expression, new Set(templateScope))).sort();
}

function getWriteTargets(expression: string, templateScope: string[] = []): string[] {
    return Array.from(collectExpressionWriteTargets(expression, new Set(templateScope))).sort();
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

    describe('write targets', () => {
        it('collects an assignment target', () => {
            expect(getWriteTargets('count = count + 1')).toEqual(['count']);
        });

        it('collects an update-expression operand', () => {
            expect(getWriteTargets('count++')).toEqual(['count']);
        });

        it('collects nothing for a read-only expression', () => {
            expect(getWriteTargets('count + 1')).toEqual([]);
        });

        it('collects several targets across statements', () => {
            expect(getWriteTargets('first = 1; second++')).toEqual([
                'first',
                'second',
            ]);
        });

        it('excludes template-scope names', () => {
            expect(
                getWriteTargets('local = 1', [
                    'local',
                ]),
            ).toEqual([]);
        });

        it('does not treat a member write as a direct identifier write', () => {
            // `count.value = 1` writes a property; `count` itself is only read (out of scope here).
            expect(getWriteTargets('count.value = 1')).toEqual([]);
        });
    });
});
