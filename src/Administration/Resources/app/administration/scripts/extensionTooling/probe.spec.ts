/**
 * @sw-package framework
 */

import path from 'path';
import {
    ESLINT_LOAD_FAILED_DETAIL,
    analyzeEslintConfigStatically,
    analyzeTsConfigStatically,
    resolveStaticEslintMode,
    resolveStaticTsMode,
    selectEslintErrorLine,
} from './probe';
import { cleanupTempProject, createTempProject, writeFile } from './test-helpers';

describe('scripts/extensionTooling/probe', () => {
    let projectRoot: string;

    beforeEach(() => {
        projectRoot = createTempProject('sw-tooling-probe-');
    });

    afterEach(() => {
        cleanupTempProject(projectRoot);
    });

    function tsconfigPath(relative: string): string {
        return path.join(projectRoot, relative);
    }

    describe('analyzeTsConfigStatically', () => {
        it('detects a bridge extends alongside an own files array', () => {
            writeFile(tsconfigPath('admin/tsconfig.json'), [
                '{',
                '    // JSONC comments must parse',
                '    "extends": "./.shopware-admin/tsconfig.json",',
                '    "files": ["../global.types.ts"],',
                '    "include": ["src/**/*"]',
                '}',
            ]);

            expect(analyzeTsConfigStatically(tsconfigPath('admin/tsconfig.json'))).toEqual({
                reachesPreset: true,
                declaresFiles: true,
                declaresPaths: false,
            });
        });

        it('follows an extends chain through intermediate configs to the preset', () => {
            writeFile(tsconfigPath('admin/tsconfig.json'), ['{ "extends": "./base/middle.json" }']);
            writeFile(tsconfigPath('admin/base/middle.json'), [
                '{ "extends": "../../extension-tooling/tsconfig.base.json" }',
            ]);
            writeFile(tsconfigPath('extension-tooling/tsconfig.base.json'), ['{}']);

            expect(analyzeTsConfigStatically(tsconfigPath('admin/tsconfig.json')).reachesPreset).toBe(true);
        });

        it('reports paths declarations and a missing extends chain', () => {
            writeFile(tsconfigPath('admin/tsconfig.json'), [
                '{ "compilerOptions": { "paths": { "MyPlugin/*": ["src/*"] } } }',
            ]);

            expect(analyzeTsConfigStatically(tsconfigPath('admin/tsconfig.json'))).toEqual({
                reachesPreset: false,
                declaresFiles: false,
                declaresPaths: true,
            });
        });

        it('surfaces a parse error for broken JSONC', () => {
            writeFile(tsconfigPath('admin/tsconfig.json'), ['{ "extends": ']);

            expect(analyzeTsConfigStatically(tsconfigPath('admin/tsconfig.json')).parseError).toBeTruthy();
        });
    });

    describe('analyzeEslintConfigStatically', () => {
        it('recognizes bridge and factory imports, rejects unrelated configs', () => {
            writeFile(tsconfigPath('bridge.mjs'), ["import shopware from './.shopware-admin/eslint.mjs';"]);
            writeFile(tsconfigPath('factory.mjs'), [
                "import { shopwareAdminExtension } from '../extension-tooling/eslint.mjs';",
            ]);
            writeFile(tsconfigPath('own.mjs'), ['export default [];']);

            expect(analyzeEslintConfigStatically(tsconfigPath('bridge.mjs')).importsFactory).toBe(true);
            expect(analyzeEslintConfigStatically(tsconfigPath('factory.mjs')).importsFactory).toBe(true);
            expect(analyzeEslintConfigStatically(tsconfigPath('own.mjs')).importsFactory).toBe(false);
        });
    });

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

    describe('static mode resolution', () => {
        it('treats a missing config as managed and verified', () => {
            expect(resolveStaticTsMode(null)).toEqual({ mode: 'managed', verified: true });
            expect(resolveStaticEslintMode(null)).toEqual({ mode: 'managed', verified: true });
        });

        it('maps analysis results to unverified modes with reasons', () => {
            writeFile(tsconfigPath('composing.json'), ['{ "extends": "./.shopware-admin/tsconfig.json" }']);
            writeFile(tsconfigPath('files-override.json'), [
                '{ "extends": "./.shopware-admin/tsconfig.json", "files": ["x.d.ts"] }',
            ]);
            writeFile(tsconfigPath('standalone.json'), ['{ "compilerOptions": { "strict": true } }']);
            writeFile(tsconfigPath('broken.json'), ['{ "extends": ']);

            expect(resolveStaticTsMode(tsconfigPath('composing.json'))).toEqual({ mode: 'bridged', verified: false });
            expect(resolveStaticTsMode(tsconfigPath('files-override.json'))).toMatchObject({
                mode: 'unmanaged',
                reason: 'files-override',
                verified: false,
            });
            expect(resolveStaticTsMode(tsconfigPath('standalone.json'))).toMatchObject({
                mode: 'unmanaged',
                reason: 'not-extending',
                verified: false,
            });
            expect(resolveStaticTsMode(tsconfigPath('broken.json'))).toMatchObject({
                mode: 'unmanaged',
                reason: 'config-error',
                verified: false,
            });

            writeFile(tsconfigPath('own-eslint.mjs'), ['export default [];']);
            expect(resolveStaticEslintMode(tsconfigPath('own-eslint.mjs'))).toMatchObject({
                mode: 'unmanaged',
                reason: 'factory-not-composed',
                verified: false,
            });
        });

        it('explains the files-override trap and points path declarers at tsconfig.aliases.json', () => {
            writeFile(tsconfigPath('trap.json'), [
                '{',
                '    "extends": "./.shopware-admin/tsconfig.json",',
                '    "files": ["x.d.ts"],',
                '    "compilerOptions": { "paths": { "MyPlugin/*": ["src/*"] } }',
                '}',
            ]);

            const resolution = resolveStaticTsMode(tsconfigPath('trap.json'));

            expect(resolution.reason).toBe('files-override');
            expect(resolution.detail).toContain('"files"');
            expect(resolution.detail).toContain('tsconfig.aliases.json');
        });
    });
});
