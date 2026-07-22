/**
 * @sw-package framework
 */

import { collectExpressionReferences } from './expression-references';

function getReferences(expression: string, templateScope: string[] = []): string[] {
    return Array.from(collectExpressionReferences(expression, new Set(templateScope))).sort();
}

describe('build/vue-setup-transform/template-analyzer/expression-references', () => {
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
});
