/**
 * @sw-package framework
 *
 * The generated projections: per-target runtime and spec leaf tsconfigs, the
 * solution-style root tsconfig that references them, the scoped root ESLint
 * config, the entity-schema gate, and the pruning of leaves whose extension
 * disappeared.
 */

import { execFileSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { setupExtensionTooling } from '../setup';
import { GENERATED_MARKER } from '../shared';
import {
    cleanupTempProject,
    syntheticEntitySchema,
    writeFile,
    writePluginsConfig,
    writeZeroConfigPlugin,
} from '../test-helpers';
import { createSetupProject, writeDefaultFixtures } from './fixtures';

describe('scripts/extensionTooling/setup config projections', () => {
    let projectRoot: string;
    let administrationRoot: string;

    beforeEach(() => {
        ({ projectRoot, administrationRoot } = createSetupProject('sw-tooling-setup-projections-'));
    });

    afterEach(() => {
        cleanupTempProject(projectRoot);
    });

    it('projects root configs that mirror the generated leaf configs', () => {
        writeDefaultFixtures(projectRoot);

        const result = setupExtensionTooling({ projectRoot, administrationRoot });
        const rootTsconfig = fs.readFileSync(path.join(projectRoot, 'tsconfig.json'), 'utf8');
        const references = [...rootTsconfig.matchAll(/"path": "(.+)"/g)].map((match) => match[1]);
        const managedTargets = result.manifest.projects.flatMap((project) =>
            project.targets.filter((target) => target.ts.mode === 'managed'),
        );

        expect(rootTsconfig).toContain(GENERATED_MARKER);
        // Each managed project contributes its runtime leaf and its spec leaf.
        expect(references).toEqual(
            managedTargets.flatMap((target) => [
                `./${target.checkTsconfig}`,
                `./${target.specTsconfig}`,
            ]),
        );

        for (const reference of references) {
            expect(fs.existsSync(path.join(projectRoot, reference))).toBe(true);
        }

        const rootEslint = fs.readFileSync(path.join(projectRoot, 'eslint.config.mjs'), 'utf8');

        for (const project of result.manifest.projects) {
            for (const target of project.targets) {
                expect(rootEslint).toContain(`${JSON.stringify(target.sourcePath)},`);
            }
        }

        const leafFiles = fs.readdirSync(path.join(projectRoot, 'var/admin-extension-tooling/projects'));

        // One runtime leaf plus one spec leaf per Administration target.
        expect(leafFiles).toHaveLength(
            result.manifest.projects.reduce((total, project) => total + project.targets.length * 2, 0),
        );

        for (const project of result.manifest.projects) {
            for (const target of project.targets) {
                expect(target.specTsconfig).toMatch(/-specs\.json$/);
                expect(fs.existsSync(path.join(projectRoot, target.specTsconfig))).toBe(true);
            }
        }
    });

    it('excludes test files from generated and scaffolded tsconfigs', () => {
        writeDefaultFixtures(projectRoot);
        setupExtensionTooling({ projectRoot, administrationRoot, shim: 'ZeroConfig' });

        const leafRaw = fs.readFileSync(
            path.join(projectRoot, 'var/admin-extension-tooling/projects/zeroconfig.json'),
            'utf8',
        );
        const leaf = JSON.parse(leafRaw.split('\n').slice(1).join('\n')) as { exclude: string[] };

        // Exclude patterns resolve relative to the config file, so the leaf
        // config (in var/) must prefix them with the source path.
        expect(leaf.exclude).toEqual(
            expect.arrayContaining([expect.stringMatching(/custom\/plugins\/ZeroConfig\/.*\*\*\/\*\.spec\.ts$/)]),
        );

        const scaffold = fs.readFileSync(
            path.join(projectRoot, 'custom/plugins/ZeroConfig/src/Resources/app/administration/tsconfig.json'),
            'utf8',
        );

        expect(scaffold).toContain('"**/*.spec.ts"');
        expect(scaffold).toContain('type-checks them separately with jest types');
    });

    it('escapes filesystem paths so a quote in an extension path cannot break the generated config', () => {
        const pluginPath = "custom/plugins/O'Brien";
        writeZeroConfigPlugin({ projectRoot, pluginPath });
        writePluginsConfig(projectRoot, [
            {
                technicalName: 'OBrien',
                basePath: `${pluginPath}/src`,
                administrationPath: 'Resources/app/administration/src',
            },
        ]);

        const result = setupExtensionTooling({ projectRoot, administrationRoot });
        const eslintConfigPath = path.join(projectRoot, 'eslint.config.mjs');
        const rootEslint = fs.readFileSync(eslintConfigPath, 'utf8');
        const sourcePath = result.manifest.projects.flatMap((project) =>
            project.targets.map((target) => target.sourcePath),
        )[0];

        expect(sourcePath).toContain("O'Brien");
        expect(rootEslint).toContain(JSON.stringify(sourcePath));
        expect(() =>
            execFileSync('node', [
                '--check',
                eslintConfigPath,
            ]),
        ).not.toThrow();
    });

    it('skips the root eslint config when no extensions are discovered', () => {
        writePluginsConfig(projectRoot, []);

        const result = setupExtensionTooling({ projectRoot, administrationRoot });

        expect(result.manifest.projects).toHaveLength(0);
        expect(result.manifest.rootConfigs.eslintConfig).toBe('skipped');
        expect(fs.existsSync(path.join(projectRoot, 'eslint.config.mjs'))).toBe(false);
    });

    it('writes an entity schema stub that keeps the entity list empty and reports unavailability', () => {
        writeDefaultFixtures(projectRoot);
        fs.rmSync(path.join(administrationRoot, 'src', 'entity-schema-definition.d.ts'));

        const result = setupExtensionTooling({ projectRoot, administrationRoot });
        const stub = fs.readFileSync(path.join(administrationRoot, 'src', 'entity-schema-definition.d.ts'), 'utf8');

        expect(result.manifest.entitySchemaAvailable).toBe(false);
        expect(stub).toContain(GENERATED_MARKER);
        expect(stub).toContain('interface Entities {}');
        // The vendor-layout fixture (admin under vendor/) resolves to the bin/console form.
        expect(result.warnings.join('\n')).toContain('bin/console administration:generate-entity-schema-types');

        // The real generated schema is never treated as a stub.
        writeFile(path.join(administrationRoot, 'src', 'entity-schema-definition.d.ts'), syntheticEntitySchema);

        const withRealSchema = setupExtensionTooling({ projectRoot, administrationRoot });

        expect(withRealSchema.manifest.entitySchemaAvailable).toBe(true);
    });

    it('removes stale leaf configs when an extension disappears', () => {
        writeDefaultFixtures(projectRoot);
        setupExtensionTooling({ projectRoot, administrationRoot });

        writePluginsConfig(projectRoot, [
            {
                technicalName: 'ZeroConfig',
                basePath: 'custom/plugins/ZeroConfig/src',
                administrationPath: 'Resources/app/administration/src',
            },
        ]);

        const result = setupExtensionTooling({ projectRoot, administrationRoot });
        const leafFiles = fs.readdirSync(path.join(projectRoot, 'var/admin-extension-tooling/projects')).sort();

        expect(result.staleFiles.length).toBeGreaterThan(0);
        // Only the surviving extension's runtime and spec leaves remain.
        expect(leafFiles).toEqual([
            'zeroconfig-specs.json',
            'zeroconfig.json',
        ]);
    });
});
