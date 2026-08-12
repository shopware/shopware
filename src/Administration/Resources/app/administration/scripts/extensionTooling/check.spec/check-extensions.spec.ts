/**
 * @sw-package framework
 */

import fs from 'fs';
import path from 'path';
import { BASELINE_FILE_NAME, writeBaselineFile } from '../baseline';
import { checkExtensions } from '../check';
import {
    cleanupTempProject,
    createSkeletonAdmin,
    createTempProject,
    realAdministrationRoot,
    syntheticEntitySchema,
    writeFile,
    writePluginsConfig,
} from '../test-helpers';

function linkRealToolchain(administrationRoot: string): void {
    fs.rmSync(path.join(administrationRoot, 'node_modules'), { recursive: true, force: true });
    fs.symlinkSync(path.join(realAdministrationRoot, 'node_modules'), path.join(administrationRoot, 'node_modules'), 'dir');
}

/**
 * Replaces the skeleton's stub extension-tooling assets with the real preset,
 * bridge factory, and type surface — required when a scaffolded (auto-bridged)
 * config must actually resolve as composing under the live probe.
 */
function copyRealTooling(administrationRoot: string): void {
    const toolingDir = path.join(administrationRoot, 'extension-tooling');

    for (const toolingFile of fs.readdirSync(path.join(realAdministrationRoot, 'extension-tooling'))) {
        fs.copyFileSync(
            path.join(realAdministrationRoot, 'extension-tooling', toolingFile),
            path.join(toolingDir, toolingFile),
        );
    }
}

describe('scripts/extensionTooling/check checkExtensions', () => {
    let projectRoot: string;
    let administrationRoot: string;

    beforeEach(() => {
        projectRoot = createTempProject('sw-tooling-check-');
        administrationRoot = createSkeletonAdmin(projectRoot);
        writeFile(path.join(administrationRoot, 'src', 'entity-schema-definition.d.ts'), syntheticEntitySchema);
    });

    afterEach(() => {
        cleanupTempProject(projectRoot);
    });

    it('refuses to autofix vendor extensions unless they are named via --only', async () => {
        // Auto-bridging scaffolds the vendor extension a composing config; the
        // real toolchain and factory let the live probe confirm composition so
        // the ESLint stream (and its vendor --fix gate) actually runs.
        copyRealTooling(administrationRoot);
        linkRealToolchain(administrationRoot);
        writeFile(path.join(projectRoot, 'vendor/acme/vendor-fix/composer.json'), '{}\n');
        writeFile(path.join(projectRoot, 'vendor/acme/vendor-fix/src/Resources/app/administration/src/main.js'), [
            'export default {};',
        ]);
        writePluginsConfig(projectRoot, [
            {
                technicalName: 'VendorFix',
                basePath: 'vendor/acme/vendor-fix/src',
                administrationPath: 'Resources/app/administration/src',
            },
        ]);

        const check = await checkExtensions({ projectRoot, administrationRoot, fix: true });

        expect(check.warnings.join('\n')).toContain('not yours to rewrite');
        expect(check.warnings.join('\n')).toContain('--only=vendor-fix');

        const explicit = await checkExtensions({
            projectRoot,
            administrationRoot,
            only: 'vendor-fix',
            fix: true,
            explicitOnly: ['vendor-fix'],
        });

        expect(explicit.warnings.join('\n')).not.toContain('not yours to rewrite');
    }, 60000);

    it('reports a vacuous TypeScript pass as no-files without spawning vue-tsc', async () => {
        // The plugin is auto-bridged (composing), so a real toolchain lets the
        // probe confirm composition; with zero type-checkable files the runtime
        // stream is empty, so vue-tsc is never spawned (durationMs 0).
        copyRealTooling(administrationRoot);
        linkRealToolchain(administrationRoot);
        writeFile(path.join(projectRoot, 'custom/plugins/JsOnly/composer.json'), '{}\n');
        writeFile(path.join(projectRoot, 'custom/plugins/JsOnly/src/Resources/app/administration/src/main.js'), [
            'export default {};',
        ]);
        writePluginsConfig(projectRoot, [
            {
                technicalName: 'JsOnly',
                basePath: 'custom/plugins/JsOnly/src',
                administrationPath: 'Resources/app/administration/src',
            },
        ]);

        const check = await checkExtensions({ projectRoot, administrationRoot });

        expect(check.results[0].typescript).toMatchObject({ status: 'no-files', durationMs: 0 });
    }, 60000);

    it('passes on an empty extension set', async () => {
        writePluginsConfig(projectRoot, []);

        const check = await checkExtensions({ projectRoot, administrationRoot });

        expect(check.results).toEqual([]);
        expect(check.exitCode).toBe(0);
        expect(check.fatalDiagnostics).toEqual([]);
    });

    it('hard-fails with the exact fix command when the entity schema is missing', async () => {
        fs.rmSync(path.join(administrationRoot, 'src', 'entity-schema-definition.d.ts'));
        writePluginsConfig(projectRoot, []);

        const check = await checkExtensions({ projectRoot, administrationRoot });

        expect(check.exitCode).toBe(1);
        expect(check.fatalDiagnostics.join('\n')).toContain('bin/console administration:generate-entity-schema-types');
    });

    it('blocks TypeScript runs entirely while the entity schema is missing', async () => {
        fs.rmSync(path.join(administrationRoot, 'src', 'entity-schema-definition.d.ts'));
        writeFile(path.join(projectRoot, 'custom/plugins/Blocked/composer.json'), '{}\n');
        writeFile(path.join(projectRoot, 'custom/plugins/Blocked/src/Resources/app/administration/src/main.ts'), [
            'export {};',
        ]);
        writePluginsConfig(projectRoot, [
            {
                technicalName: 'Blocked',
                basePath: 'custom/plugins/Blocked/src',
                administrationPath: 'Resources/app/administration/src',
            },
        ]);

        const check = await checkExtensions({ projectRoot, administrationRoot });

        // durationMs 0 proves vue-tsc was never spawned (the skeleton admin has
        // no real toolchain — a spawn attempt would surface as tooling-error).
        expect(check.results[0].typescript).toMatchObject({ status: 'blocked', durationMs: 0 });
        expect(check.results[0].eslint.status).not.toBe('blocked');
        expect(check.exitCode).toBe(1);
    });

    it('hard-fails when a root config is user-owned', async () => {
        writePluginsConfig(projectRoot, []);
        writeFile(path.join(projectRoot, 'tsconfig.json'), '{"compilerOptions":{}}\n');

        const check = await checkExtensions({ projectRoot, administrationRoot });

        expect(check.exitCode).toBe(1);
        expect(check.fatalDiagnostics.join('\n')).toContain('root tsconfig.json is user-owned');
    });

    it('hard-fails when --only matches no extension', async () => {
        writePluginsConfig(projectRoot, []);

        const check = await checkExtensions({ projectRoot, administrationRoot, only: 'Nope' });

        expect(check.exitCode).toBe(1);
        expect(check.fatalDiagnostics.join('\n')).toContain('unknown extension(s): Nope');
    });

    it('reports every requested name when an array selection matches nothing', async () => {
        writePluginsConfig(projectRoot, []);

        const check = await checkExtensions({
            projectRoot,
            administrationRoot,
            only: [
                'Nope',
                'Nada',
            ],
        });

        expect(check.exitCode).toBe(1);
        expect(check.fatalDiagnostics.join('\n')).toContain('unknown extension(s): Nope, Nada');
    });

    it('rejects the whole run when --only mixes a valid name with an unknown one', async () => {
        writeFile(path.join(projectRoot, 'custom/plugins/Known/composer.json'), '{}\n');
        writeFile(path.join(projectRoot, 'custom/plugins/Known/src/Resources/app/administration/src/main.ts'), [
            'export {};',
        ]);
        writePluginsConfig(projectRoot, [
            {
                technicalName: 'Known',
                basePath: 'custom/plugins/Known/src',
                administrationPath: 'Resources/app/administration/src',
            },
        ]);

        const check = await checkExtensions({
            projectRoot,
            administrationRoot,
            only: [
                'Known',
                'Typo',
            ],
        });

        // A single typo fails and names the unknown subset; the valid target is
        // not silently checked-and-passed while the typo goes unnoticed.
        expect(check.exitCode).toBe(1);
        expect(check.fatalDiagnostics.join('\n')).toContain('unknown extension(s): Typo');
        expect(check.results).toEqual([]);
    });

    it('filters to multiple extensions from a comma-separated selection', async () => {
        for (const name of [
            'Alpha',
            'Bravo',
            'Charlie',
        ]) {
            writeFile(path.join(projectRoot, `custom/plugins/${name}/composer.json`), '{}\n');
            writeFile(path.join(projectRoot, `custom/plugins/${name}/src/Resources/app/administration/src/main.ts`), [
                'export {};',
            ]);
        }

        writePluginsConfig(
            projectRoot,
            [
                'Alpha',
                'Bravo',
                'Charlie',
            ].map((name) => ({
                technicalName: name,
                basePath: `custom/plugins/${name}/src`,
                administrationPath: 'Resources/app/administration/src',
            })),
        );

        const check = await checkExtensions({ projectRoot, administrationRoot, only: 'Alpha,Charlie' });

        expect(check.results.map((result) => result.project.name).sort()).toEqual([
            'Alpha',
            'Charlie',
        ]);
    });

    it('scopes setup warnings to the selection but keeps the project-global ones', async () => {
        // Setup always runs over every installed extension, so without scoping
        // a --only run would report extensions it never checks.
        fs.rmSync(path.join(administrationRoot, 'src', 'entity-schema-definition.d.ts'));

        for (const name of [
            'Selected',
            'Unwired',
        ]) {
            writeFile(path.join(projectRoot, `custom/plugins/${name}/composer.json`), '{}\n');
            writeFile(path.join(projectRoot, `custom/plugins/${name}/src/Resources/app/administration/src/main.ts`), [
                'export {};',
            ]);
        }

        writeFile(path.join(projectRoot, 'custom/plugins/Unwired/src/Resources/app/administration/tsconfig.json'), [
            '{ "compilerOptions": { "strict": true } }',
        ]);
        writePluginsConfig(
            projectRoot,
            [
                'Selected',
                'Unwired',
            ].map((name) => ({
                technicalName: name,
                basePath: `custom/plugins/${name}/src`,
                administrationPath: 'Resources/app/administration/src',
            })),
        );

        const all = await checkExtensions({ projectRoot, administrationRoot });

        expect(all.warnings.join('\n')).toContain('Unwired');

        const scoped = await checkExtensions({ projectRoot, administrationRoot, only: 'Selected' });

        expect(scoped.warnings.join('\n')).not.toContain('Unwired');
        // A missing entity schema is not one extension's problem — it affects
        // the selected run too, so it must survive the filter.
        expect(scoped.warnings.join('\n')).toContain('Entity schema types are not available');
    });

    it('visibly skips custom configs that do not compose the Shopware preset', async () => {
        linkRealToolchain(administrationRoot);
        writeFile(path.join(projectRoot, 'custom/plugins/Probe/composer.json'), '{}\n');
        writeFile(path.join(projectRoot, 'custom/plugins/Probe/src/Resources/app/administration/src/main.ts'), [
            'export {};',
        ]);
        writeFile(path.join(projectRoot, 'custom/plugins/Probe/src/Resources/app/administration/tsconfig.json'), [
            '{',
            '    "compilerOptions": { "strict": true, "noEmit": true },',
            '    "include": ["src/**/*.ts"]',
            '}',
        ]);
        writeFile(path.join(projectRoot, 'custom/plugins/Probe/src/Resources/app/administration/eslint.config.mjs'), [
            'export default [{ rules: {} }];',
        ]);
        writePluginsConfig(projectRoot, [
            {
                technicalName: 'Probe',
                basePath: 'custom/plugins/Probe/src',
                administrationPath: 'Resources/app/administration/src',
            },
        ]);

        const check = await checkExtensions({ projectRoot, administrationRoot });
        const probe = check.results[0];

        expect(check.results).toHaveLength(1);
        expect(probe.tsResolution?.composes).toBe(false);
        expect(probe.tsResolution?.detail).toContain('extends chain does not reach');
        expect(probe.eslintResolution?.composes).toBe(false);
        expect(probe.eslintResolution?.detail).toContain('does not compose the Shopware factory');
        expect(probe.typescript.status).toBe('unmanaged');
        expect(probe.eslint.status).toBe('unmanaged');
        expect(check.exitCode).toBe(0);

        // Same skipped writable extension is a hard failure under --fail-on-skipped.
        const strict = await checkExtensions({ projectRoot, administrationRoot, failOnSkipped: true });

        expect(strict.results[0].typescript.status).toBe('unmanaged');
        expect(strict.exitCode).toBe(1);

        writeBaselineFile(projectRoot, probe.project, {
            version: 1,
            typescript: [{ file: 'src/main.ts', code: 'TS2322', message: 'existing debt', count: 1 }],
            typescriptSpecs: [],
            eslint: [],
        });
        const baselinePath = path.join(projectRoot, probe.project.basePath, BASELINE_FILE_NAME);
        const baselineBefore = fs.readFileSync(baselinePath);
        const refusedUpdate = await checkExtensions({
            projectRoot,
            administrationRoot,
            updateBaseline: true,
        });

        expect(refusedUpdate.exitCode).toBe(1);
        expect(refusedUpdate.fatalDiagnostics.join('\n')).toContain('baseline not updated');
        expect(fs.readFileSync(baselinePath)).toEqual(baselineBefore);
    }, 60000);
});
