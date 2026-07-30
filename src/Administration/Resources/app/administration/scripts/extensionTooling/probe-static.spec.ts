/**
 * @sw-package framework
 */

import path from 'path';
import {
    analyzeEslintConfigStatically,
    analyzeTsConfigStatically,
    resolveStaticEslintMode,
    resolveStaticTsMode,
} from './probe-static';
import { cleanupTempProject, createTempProject, writeFile } from './test-helpers';

describe('scripts/extensionTooling/probe-static', () => {
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
                '    "extends": "./.shopware/tsconfig.json",',
                '    "files": ["../global.types.ts"],',
                '    "include": ["src/**/*"]',
                '}',
            ]);

            expect(analyzeTsConfigStatically(tsconfigPath('admin/tsconfig.json'))).toEqual({
                reachesPreset: true,
                reachesLegacyPreset: false,
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
                reachesLegacyPreset: false,
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
            writeFile(tsconfigPath('bridge.mjs'), ["import shopware from './.shopware/eslint.mjs';"]);
            writeFile(tsconfigPath('factory.mjs'), [
                "import { shopwareAdminExtension } from '../extension-tooling/eslint.mjs';",
            ]);
            writeFile(tsconfigPath('own.mjs'), ['export default [];']);

            expect(analyzeEslintConfigStatically(tsconfigPath('bridge.mjs')).importsFactory).toBe(true);
            expect(analyzeEslintConfigStatically(tsconfigPath('factory.mjs')).importsFactory).toBe(true);
            expect(analyzeEslintConfigStatically(tsconfigPath('own.mjs')).importsFactory).toBe(false);
        });
    });

    describe('static mode resolution', () => {
        it('treats a missing config as managed and verified', () => {
            expect(resolveStaticTsMode(null)).toEqual({ mode: 'managed', verified: true });
            expect(resolveStaticEslintMode(null)).toEqual({ mode: 'managed', verified: true });
        });

        it('maps analysis results to unverified modes with reasons', () => {
            writeFile(tsconfigPath('composing.json'), ['{ "extends": "./.shopware/tsconfig.json" }']);
            writeFile(tsconfigPath('files-override.json'), [
                '{ "extends": "./.shopware/tsconfig.json", "files": ["x.d.ts"] }',
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

        it('points configs at the renamed bridge when they still reference the legacy directory', () => {
            writeFile(tsconfigPath('legacy.json'), ['{ "extends": "./.shopware-admin/tsconfig.json" }']);
            writeFile(tsconfigPath('legacy-eslint.mjs'), ["import shopware from './.shopware-admin/eslint.mjs';"]);

            const tsResolution = resolveStaticTsMode(tsconfigPath('legacy.json'));

            expect(tsResolution.reason).toBe('not-extending');
            expect(tsResolution.detail).toContain('.shopware-admin');
            expect(tsResolution.detail).toContain('"./.shopware/tsconfig.json"');

            const eslintResolution = resolveStaticEslintMode(tsconfigPath('legacy-eslint.mjs'));

            expect(eslintResolution.reason).toBe('factory-not-composed');
            expect(eslintResolution.detail).toContain('.shopware-admin');
            expect(eslintResolution.detail).toContain("'./.shopware/eslint.mjs'");
        });

        it('explains the files-override trap and points path declarers at tsconfig.aliases.json', () => {
            writeFile(tsconfigPath('trap.json'), [
                '{',
                '    "extends": "./.shopware/tsconfig.json",',
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
