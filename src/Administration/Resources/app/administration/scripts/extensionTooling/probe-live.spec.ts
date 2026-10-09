/**
 * @sw-package framework
 */

import { ESLINT_LOAD_FAILED_DETAIL, selectEslintErrorLine } from './probe-live';

describe('scripts/extensionTooling/probe-live', () => {
    describe('selectEslintErrorLine', () => {
        it('skips the generic banner and surfaces the real ERR_ line', () => {
            const output = [
                'Oops! Something went wrong! :(',
                '',
                'ESLint: 9.39.3',
                '',
                "Error [ERR_MODULE_NOT_FOUND]: Cannot find module '/…/twigVuePlugin/lib/index.js'",
                '    imported from /…/SwagPayPal/…/eslint.config.mjs',
            ].join('\n');

            const detail = selectEslintErrorLine(output);

            expect(detail).toContain('ERR_MODULE_NOT_FOUND');
            expect(detail).not.toContain('Oops!');
        });

        it('matches other error classes and indented ERR_ codes', () => {
            expect(selectEslintErrorLine('Oops! Something went wrong! :(\nTypeError: x is not a function')).toBe(
                'TypeError: x is not a function',
            );
            expect(selectEslintErrorLine("Oops!\n    code: 'ERR_REQUIRE_ESM'")).toContain('ERR_REQUIRE_ESM');
        });

        it('falls back to a --verbose hint when no error line is recognizable', () => {
            expect(selectEslintErrorLine('Oops! Something went wrong! :(\n\nESLint couldn’t find a config')).toBe(
                ESLINT_LOAD_FAILED_DETAIL,
            );
            expect(selectEslintErrorLine('')).toBe(ESLINT_LOAD_FAILED_DETAIL);
        });
    });
});
