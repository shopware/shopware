/**
 * @sw-package framework
 */

import { addPatternNames, collectPatternReferences, parseBindingPattern } from './references';

/** Names a binding pattern declares. */
function declaredNames(patternSource: string): string[] {
    const { pattern } = parseBindingPattern(patternSource);
    const names = new Set<string>();
    addPatternNames(pattern, names);

    return Array.from(names).sort();
}

/** Outer-scope names a binding pattern reads (defaults, computed keys). */
function patternReferences(patternSource: string, outerScope: string[] = []): string[] {
    const { pattern } = parseBindingPattern(patternSource);
    const references = new Set<string>();
    collectPatternReferences(
        pattern,
        [
            new Set(outerScope),
        ],
        references,
    );

    return Array.from(references).sort();
}

describe('build/vue-setup-transform/flow-analysis binding patterns', () => {
    describe('addPatternNames', () => {
        it('collects object, aliased, and rest names', () => {
            expect(declaredNames('{ a, b: c, ...rest }')).toEqual([
                'a',
                'c',
                'rest',
            ]);
        });

        it('collects array-pattern names including defaults and holes', () => {
            expect(declaredNames('[first, , third = fallback]')).toEqual([
                'first',
                'third',
            ]);
        });

        it('does not collect a default value as a declared name', () => {
            expect(declaredNames('{ label = fallback }')).toEqual(['label']);
        });
    });

    describe('collectPatternReferences', () => {
        it('reads a destructuring default from outer scope', () => {
            expect(patternReferences('{ label = fallback }')).toEqual(['fallback']);
        });

        it('reads a computed key from outer scope', () => {
            expect(patternReferences('{ [key]: value }')).toEqual(['key']);
        });

        it('lets an earlier pattern name shadow a later default', () => {
            expect(patternReferences('{ a, b = a }')).toEqual([]);
        });

        it('excludes names already in an outer scope', () => {
            expect(
                patternReferences('{ label = fallback }', [
                    'fallback',
                ]),
            ).toEqual([]);
        });
    });
});
