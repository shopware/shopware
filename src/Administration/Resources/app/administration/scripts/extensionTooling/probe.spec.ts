/**
 * @sw-package framework
 */

import path from 'path';
import {
    ESLINT_LOAD_FAILED_DETAIL,
    analyzeEslintConfigStatically,
    analyzeTsConfigStatically,
    probeCacheEntryKey,
    probeCacheKey,
    probeInputFiles,
    readProbeCache,
    resolveModesFromCache,
    resolveStaticEslintMode,
    resolveStaticTsMode,
    selectEslintErrorLine,
    writeProbeCache,
} from './probe';
import type { ProbeCacheFile } from './probe';
import type { AdministrationTarget } from './shared';
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

    describe('probe cache', () => {
        function fixtureTarget(overrides: Partial<AdministrationTarget> = {}): AdministrationTarget {
            return {
                technicalNames: ['Probe'],
                sourcePath: 'custom/plugins/Probe/src/Resources/app/administration/src',
                adminFolder: 'custom/plugins/Probe/src/Resources/app/administration',
                bridgePresent: false,
                tsconfig: 'custom/plugins/Probe/src/Resources/app/administration/tsconfig.json',
                eslintConfig: null,
                ts: { mode: 'unmanaged', reason: 'not-extending', verified: false },
                eslint: { mode: 'managed', verified: true },
                checkTsconfig: '',
                specTsconfig: '',
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

        it('invalidates the cached TypeScript verdict when an indirectly extended config changes', () => {
            const administrationRoot = path.join(projectRoot, 'admin');
            const target = fixtureTarget();
            const cacheKey = probeCacheEntryKey('Probe', target);
            const adminFolder = path.join(projectRoot, target.adminFolder);

            writeFile(path.join(adminFolder, 'base.json'), ['{ "compilerOptions": { "strict": true } }']);
            writeFile(path.join(projectRoot, target.tsconfig as string), ['{ "extends": "./base.json" }']);

            const inputs = probeInputFiles(target, projectRoot, administrationRoot);

            expect(inputs.ts).toContain(path.join(adminFolder, 'base.json'));

            const cache: ProbeCacheFile = {
                version: 2,
                entries: {
                    [cacheKey]: { ts: { key: probeCacheKey(inputs.ts), resolution: { mode: 'bridged', verified: true } } },
                },
            };

            expect(resolveModesFromCache(target, cacheKey, cache, projectRoot, administrationRoot).ts.verified).toBe(true);

            // Editing the indirectly extended base must drop the cached verdict.
            writeFile(path.join(adminFolder, 'base.json'), ['{ "compilerOptions": { "strict": false } }']);

            expect(resolveModesFromCache(target, cacheKey, cache, projectRoot, administrationRoot).ts).toEqual(target.ts);
        });

        it('invalidates the cached ESLint verdict when a locally imported config module changes', () => {
            const administrationRoot = path.join(projectRoot, 'admin');
            const target = fixtureTarget({
                eslintConfig: 'custom/plugins/Probe/src/Resources/app/administration/eslint.config.mjs',
                eslint: { mode: 'bridged', verified: false },
            });
            const cacheKey = probeCacheEntryKey('Probe', target);
            const adminFolder = path.join(projectRoot, target.adminFolder);

            writeFile(path.join(adminFolder, 'local-rules.mjs'), ['export default [];']);
            writeFile(path.join(projectRoot, target.eslintConfig as string), [
                "import local from './local-rules.mjs';",
                'export default [...local];',
            ]);

            const inputs = probeInputFiles(target, projectRoot, administrationRoot);

            expect(inputs.eslint).toContain(path.join(adminFolder, 'local-rules.mjs'));

            const cache: ProbeCacheFile = {
                version: 2,
                entries: {
                    [cacheKey]: {
                        eslint: { key: probeCacheKey(inputs.eslint), resolution: { mode: 'bridged', verified: true } },
                    },
                },
            };

            expect(resolveModesFromCache(target, cacheKey, cache, projectRoot, administrationRoot).eslint.verified).toBe(
                true,
            );

            writeFile(path.join(adminFolder, 'local-rules.mjs'), ['export default [{ rules: {} }];']);

            expect(resolveModesFromCache(target, cacheKey, cache, projectRoot, administrationRoot).eslint).toEqual(
                target.eslint,
            );
        });

        it('reads back what it wrote and rejects garbage or unknown versions', () => {
            const cache: ProbeCacheFile = {
                version: 2,
                entries: { Probe: { ts: { key: 'k', resolution: { mode: 'bridged', verified: true } } } },
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
            const target = fixtureTarget();
            const cacheKey = probeCacheEntryKey('Probe', target);

            writeFile(path.join(projectRoot, target.tsconfig as string), ['{ "compilerOptions": {} }']);

            const inputs = probeInputFiles(target, projectRoot, administrationRoot);
            const cache: ProbeCacheFile = {
                version: 2,
                entries: {
                    [cacheKey]: {
                        ts: {
                            key: probeCacheKey(inputs.ts),
                            resolution: { mode: 'unmanaged', reason: 'surface-not-injected', verified: true },
                        },
                    },
                },
            };

            const adopted = resolveModesFromCache(target, cacheKey, cache, projectRoot, administrationRoot);

            expect(adopted.ts).toEqual({ mode: 'unmanaged', reason: 'surface-not-injected', verified: true });
            expect(adopted.eslint).toEqual(target.eslint);

            writeFile(path.join(projectRoot, target.tsconfig as string), ['{ "compilerOptions": { "x": 1 } }']);

            const stale = resolveModesFromCache(target, cacheKey, cache, projectRoot, administrationRoot);

            expect(stale.ts).toEqual(target.ts);
        });
    });
});
