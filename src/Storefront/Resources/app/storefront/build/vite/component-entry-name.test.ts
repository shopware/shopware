import { describe, expect, it, vi } from 'vitest';
import { normalizeComponentEntryName, warnForDuplicateEntryNames } from './component-entry-name.cjs';

describe('normalizeComponentEntryName', () => {
    it('strips a trailing index file segment from script paths', () => {
        expect(normalizeComponentEntryName('Sw/Foo/index.js')).toBe('Sw/Foo');
        expect(normalizeComponentEntryName('Sw/Foo/index.ts')).toBe('Sw/Foo');
    });

    it('keeps the style extension while stripping a trailing index segment', () => {
        expect(normalizeComponentEntryName('Sw/Foo/index.scss', true)).toBe('Sw/Foo.scss');
        expect(normalizeComponentEntryName('Sw/Foo/index.css', true)).toBe('Sw/Foo.css');
    });

    it('keeps flat entry paths unchanged apart from their script extension', () => {
        expect(normalizeComponentEntryName('Sw/Foo.js')).toBe('Sw/Foo');
        expect(normalizeComponentEntryName('Sw/Foo.scss', true)).toBe('Sw/Foo.scss');
    });

    it('warns when files produce the same normalized entry name without throwing', () => {
        const warning = vi.spyOn(console, 'warn').mockImplementation(() => undefined);

        try {
            expect(() => warnForDuplicateEntryNames(
                ['Sw/Foo.js', 'Sw/Foo/index.js'],
                normalizeComponentEntryName,
                '[component-entries]',
                'JavaScript',
            )).not.toThrow();
            expect(warning).toHaveBeenCalledWith(expect.stringContaining('[WARNING]'));
            expect(warning).toHaveBeenCalledWith(expect.stringContaining('JavaScript entry "Sw/Foo"'));
            expect(warning).toHaveBeenCalledWith(expect.stringContaining('The latter will replace the former.'));
        } finally {
            warning.mockRestore();
        }
    });
});
