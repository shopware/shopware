/**
 * @sw-package framework
 *
 * Direct coverage for transformRanges' overlap handling. The "partially overlapping" throw is reachable
 * only from an analyzer bug, and the "contained range is skipped" branch is a real behaviour base
 * lowering depends on (a rename edit inside a removed marker statement) - both worth pinning directly.
 */

import { transformRanges } from './transform-ranges';
import { render } from './render-chunks';

function block(content: string) {
    return { contentStart: 0, content };
}

describe('build/vue-setup-transform source-edits/transform-ranges', () => {
    it('keeps untouched source and applies a generated replacement', () => {
        const content = 'const a = 1;';
        const chunks = transformRanges(block(content), [], [{ start: 6, end: 7, replacement: 'X' }]);

        expect(render(chunks, content)).toBe('const X = 1;');
    });

    it('skips a range fully contained in an earlier removal (rename inside a removed marker)', () => {
        const content = 'const a = 1;';
        // The whole statement is removed; a replacement inside it is moot and is deliberately dropped
        // rather than treated as an overlap error.
        const chunks = transformRanges(
            block(content),
            [{ start: 0, end: content.length }],
            [{ start: 6, end: 7, replacement: 'X' }],
        );

        expect(render(chunks, content)).toBe('');
    });

    it('throws a named analyzer-bug error when a range straddles a removal boundary', () => {
        const content = 'abcdefghij';

        expect(() =>
            transformRanges(block(content), [{ start: 0, end: 5 }], [{ start: 3, end: 8, replacement: 'X' }]),
        ).toThrow('Partially overlapping Shopware setup source edits');
    });
});
