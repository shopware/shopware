/**
 * @sw-package framework
 */

import path from 'path';
import {
    analyzeEslintConfigStatically,
    analyzeTsConfigStatically,
    probeCacheKey,
    probeInputFiles,
    readProbeCache,
    resolveModesFromCache,
    resolveStaticEslintMode,
    resolveStaticTsMode,
    writeProbeCache,
} from './probe';
import type { ProbeCacheFile } from './probe';
import type { ExtensionToolingProject } from './shared';
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

            expect(resolveStaticTsMode(tsconfigPath('composing.json'))).toEqual({ mode: 'custom', verified: false });
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

    describe('probe cache', () => {
        function fixtureProject(overrides: Partial<ExtensionToolingProject> = {}): ExtensionToolingProject {
            return {
                name: 'Probe',
                technicalNames: ['Probe'],
                basePath: 'custom/plugins/Probe',
                sourcePaths: ['custom/plugins/Probe/src/Resources/app/administration/src'],
                vendor: false,
                bridgePresent: false,
                tsconfig: 'custom/plugins/Probe/src/Resources/app/administration/tsconfig.json',
                eslintConfig: null,
                ts: { mode: 'unmanaged', reason: 'not-extending', verified: false },
                eslint: { mode: 'managed', verified: true },
                checkTsconfig: '',
                ...overrides,
            };
        }

        it('changes the cache key when any input file content changes', () => {
            writeFile(tsconfigPath('a.json'), ['{}']);

            const missingTolerated = probeCacheKey([
                tsconfigPath('a.json'),
                tsconfigPath('does-not-exist.json'),
            ]);
            const before = probeCacheKey([tsconfigPath('a.json')]);

            writeFile(tsconfigPath('a.json'), ['{ "changed": true }']);

            expect(probeCacheKey([tsconfigPath('a.json')])).not.toBe(before);
            expect(missingTolerated).toEqual(expect.any(String));
        });

        it('reads back what it wrote and rejects garbage or unknown versions', () => {
            const cache: ProbeCacheFile = {
                version: 1,
                entries: { Probe: { ts: { key: 'k', resolution: { mode: 'custom', verified: true } } } },
            };

            writeProbeCache(projectRoot, cache);
            expect(readProbeCache(projectRoot)).toEqual(cache);

            writeFile(path.join(projectRoot, 'var/admin-extension-tooling/probe-cache.json'), ['not json {{']);
            expect(readProbeCache(projectRoot)).toBeNull();

            writeFile(path.join(projectRoot, 'var/admin-extension-tooling/probe-cache.json'), [
                '{ "version": 99, "entries": {} }',
            ]);
            expect(readProbeCache(projectRoot)).toBeNull();
        });

        it('adopts a cached verdict only while the content hashes match', () => {
            const administrationRoot = path.join(projectRoot, 'admin');
            const project = fixtureProject();

            writeFile(path.join(projectRoot, project.tsconfig as string), ['{ "compilerOptions": {} }']);

            const inputs = probeInputFiles(project, projectRoot, administrationRoot);
            const cache: ProbeCacheFile = {
                version: 1,
                entries: {
                    Probe: {
                        ts: {
                            key: probeCacheKey(inputs.ts),
                            resolution: { mode: 'unmanaged', reason: 'surface-not-injected', verified: true },
                        },
                    },
                },
            };

            const adopted = resolveModesFromCache(project, cache, projectRoot, administrationRoot);

            expect(adopted.ts).toEqual({ mode: 'unmanaged', reason: 'surface-not-injected', verified: true });
            expect(adopted.eslint).toEqual(project.eslint);

            writeFile(path.join(projectRoot, project.tsconfig as string), ['{ "compilerOptions": { "x": 1 } }']);

            const stale = resolveModesFromCache(project, cache, projectRoot, administrationRoot);

            expect(stale.ts).toEqual(project.ts);
        });
    });
});
