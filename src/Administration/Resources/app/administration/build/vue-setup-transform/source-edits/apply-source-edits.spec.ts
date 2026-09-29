/**
 * @sw-package framework
 *
 * Direct coverage for the internal invariants of applySourceEdits. These paths are reachable only from
 * an analyzer bug (no authoring input can produce overlapping edits), so nothing in the SFC-level suite
 * exercises them - which is exactly why the guardrail needs its own test.
 */

import { applySourceEdits } from './apply-source-edits';

describe('build/vue-setup-transform source-edits/apply-source-edits', () => {
    it('applies non-overlapping edits in order and produces a sourcemap', () => {
        const result = applySourceEdits('0123456789', 'test.vue', [
            { start: 2, end: 4, replacement: 'AB' },
            { start: 6, end: 8, replacement: 'CD' },
        ]);

        expect(result.code).toBe('01AB45CD89');
        expect(result.map).not.toBeNull();
    });

    it('throws a named analyzer-bug error when two edits overlap', () => {
        expect(() =>
            applySourceEdits('0123456789', 'test.vue', [
                { start: 2, end: 6, replacement: 'X' },
                { start: 4, end: 8, replacement: 'Y' },
            ]),
        ).toThrow('Overlapping Shopware setup source edits');
    });

    it('allows a zero-width insertion sharing a position with the following edit', () => {
        // The generated registration template for a template-less override relies on this: a 0..0
        // insertion at the same offset as the next real edit must not count as an overlap.
        const result = applySourceEdits('abc', 'test.vue', [
            { start: 0, end: 0, replacement: '<!--x-->' },
            { start: 0, end: 1, replacement: 'A' },
        ]);

        expect(result.code).toBe('<!--x-->Abc');
    });
});
