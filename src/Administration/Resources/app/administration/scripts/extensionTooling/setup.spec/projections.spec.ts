/**
 * @sw-package framework
 *
 * The generated projections: the solution-style root tsconfig (routing only
 * fallback leaf tsconfigs — bridged targets carry their own configs), the
 * scoped root ESLint config, the entity-schema gate, and the pruning of stale
 * leaves.
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

    it('routes bridged targets to their own configs and writes no fallback leafs', () => {
        writeDefaultFixtures(projectRoot);

        const result = setupExtensionTooling({ projectRoot, administrationRoot });
        const rootTsconfig = fs.readFileSync(path.join(projectRoot, 'tsconfig.json'), 'utf8');
        const references = [...rootTsconfig.matchAll(/"path": "(.+)"/g)].map((match) => match[1]);

        expect(rootTsconfig).toContain(GENERATED_MARKER);
        // Auto-bridging gave every target an owned (scaffolded or pre-existing)
        // config, so the solution index has nothing left to route and no
        // fallback leafs are generated.
        expect(references).toEqual([]);
        expect(fs.existsSync(path.join(projectRoot, 'var/admin-extension-tooling/projects'))).toBe(false);

        for (const project of result.manifest.projects) {
            for (const target of project.targets) {
                expect(target.checkTsconfig).toBe(target.tsconfig);
            }
        }

        const rootEslint = fs.readFileSync(path.join(projectRoot, 'eslint.config.mjs'), 'utf8');

        for (const project of result.manifest.projects) {
            for (const target of project.targets) {
                expect(rootEslint).toContain(`${JSON.stringify(target.sourcePath)},`);
            }
        }
    });

    it('excludes test files from scaffolded tsconfigs', () => {
        writeDefaultFixtures(projectRoot);
        setupExtensionTooling({ projectRoot, administrationRoot });

        const scaffold = fs.readFileSync(
            path.join(projectRoot, 'custom/plugins/ZeroConfig/src/Resources/app/administration/tsconfig.json'),
            'utf8',
        );

        expect(scaffold).toContain('"**/*.spec.ts"');
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

    it('prunes leaf configs left behind by previous runs', () => {
        writeDefaultFixtures(projectRoot);
        // A leaf of a removed extension — or of a target that has since been
        // bridged and no longer needs the fallback.
        writeFile(path.join(projectRoot, 'var/admin-extension-tooling/projects/gone.json'), [
            `// ${GENERATED_MARKER}`,
            '{}',
        ]);

        const result = setupExtensionTooling({ projectRoot, administrationRoot });

        expect(result.staleFiles).toContain('var/admin-extension-tooling/projects/gone.json');
        expect(fs.existsSync(path.join(projectRoot, 'var/admin-extension-tooling/projects/gone.json'))).toBe(false);
    });
});
