/**
 * @sw-package framework
 */

import fs from 'fs';
import path from 'path';
import { checkExtensions } from '../check';
import { setupExtensionTooling } from '../setup';
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
    });

    it('reports a vacuous TypeScript pass as no-files without spawning vue-tsc', async () => {
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

        // The skeleton admin has no vue-tsc — a spawn attempt would surface as
        // tooling-error, so no-files proves the run was skipped.
        expect(check.results[0].typescript).toMatchObject({ status: 'no-files', durationMs: 0 });
    });

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
        expect(check.fatalDiagnostics.join('\n')).toContain('composer admin:generate-entity-schema-types');
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
        expect(check.fatalDiagnostics.join('\n')).toContain('--only=Nope');
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
        expect(check.fatalDiagnostics.join('\n')).toContain('--only=Nope,Nada');
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
        expect(probe.tsResolution.mode).toBe('unmanaged');
        expect(probe.tsResolution.reason).toBe('not-extending');
        expect(probe.eslintResolution.mode).toBe('unmanaged');
        expect(probe.eslintResolution.reason).toBe('factory-not-composed');
        expect(probe.tsResolution.verified).toBe(true);
        expect(probe.typescript.status).toBe('unmanaged');
        expect(probe.eslint.status).toBe('unmanaged');
        expect(check.exitCode).toBe(0);

        // The verified verdicts are cached, and a subsequent setup run renders
        // the same state instead of contradicting the check.
        expect(fs.existsSync(path.join(projectRoot, 'var/admin-extension-tooling/probe-cache.json'))).toBe(true);

        const followUpSetup = setupExtensionTooling({ projectRoot, administrationRoot });
        const followUpProject = followUpSetup.manifest.projects[0];

        expect(followUpProject.ts).toMatchObject({ mode: 'unmanaged', verified: true });
        expect(followUpProject.eslint).toMatchObject({ mode: 'unmanaged', verified: true });
    }, 60000);
});
