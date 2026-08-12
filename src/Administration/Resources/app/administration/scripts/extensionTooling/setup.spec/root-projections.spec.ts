/**
 * @sw-package framework
 *
 * The host-owned root files: the tsconfig projection that covers whatever no
 * extension config governs, the scoped root ESLint config, the entity-schema
 * gate, and the write-ownership contract they all share (user-owned files are
 * never touched, marker-owned ones are rewritten, a second run changes
 * nothing, and --check writes nothing at all).
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
    warningText,
    writePluginsConfig,
    writeZeroConfigPlugin,
} from '../test-helpers';
import { createSetupProject, writeDefaultFixtures } from './fixtures';

describe('scripts/extensionTooling/setup root projections', () => {
    let projectRoot: string;
    let administrationRoot: string;

    beforeEach(() => {
        ({ projectRoot, administrationRoot } = createSetupProject('sw-tooling-setup-projections-'));
    });

    afterEach(() => {
        cleanupTempProject(projectRoot);
    });

    function readRootTsconfig(): { extends: string; files: string[]; include: string[]; exclude: string[] } {
        const raw = fs.readFileSync(path.join(projectRoot, 'tsconfig.json'), 'utf8');

        return JSON.parse(raw.split('\n').slice(1).join('\n')) as ReturnType<typeof readRootTsconfig>;
    }

    it('leaves the root tsconfig empty once every extension owns a composing config', () => {
        writeDefaultFixtures(projectRoot);

        const result = setupExtensionTooling({ projectRoot, administrationRoot });
        const rootTsconfig = readRootTsconfig();

        expect(fs.readFileSync(path.join(projectRoot, 'tsconfig.json'), 'utf8')).toContain(GENERATED_MARKER);
        expect(rootTsconfig.extends).toContain('extension-tooling/tsconfig.base.json');
        expect(rootTsconfig.files[0]).toContain('extension-tooling/admin-types.d.ts');
        // Bridging gave every target its own config, so nothing is left to cover.
        expect(rootTsconfig.include).toEqual([]);
        // The leaf-config directory of earlier versions is not recreated.
        expect(fs.existsSync(path.join(projectRoot, 'var/admin-extension-tooling/projects'))).toBe(false);

        const rootEslint = fs.readFileSync(path.join(projectRoot, 'eslint.config.mjs'), 'utf8');

        for (const project of result.manifest.projects) {
            for (const target of project.targets) {
                expect(rootEslint).toContain(`${JSON.stringify(target.sourcePath)},`);
            }
        }
    });

    it('excludes test files from the scaffolded committed tsconfig — the spec program checks them instead', () => {
        writeDefaultFixtures(projectRoot);
        setupExtensionTooling({ projectRoot, administrationRoot });

        const scaffold = fs.readFileSync(
            path.join(projectRoot, 'custom/plugins/ZeroConfig/src/Resources/app/administration/tsconfig.json'),
            'utf8',
        );

        // The runtime config excludes specs; the generated .shopware/ spec
        // program (spec-tsconfig.spec.ts) type-checks them with jest types.
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
        const sourcePath = result.manifest.projects.flatMap((project) =>
            project.targets.map((target) => target.sourcePath),
        )[0];

        expect(sourcePath).toContain("O'Brien");
        expect(fs.readFileSync(eslintConfigPath, 'utf8')).toContain(JSON.stringify(sourcePath));
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
        expect(warningText(result)).toContain('bin/console administration:generate-entity-schema-types');

        // The real generated schema is never treated as a stub.
        writeFile(path.join(administrationRoot, 'src', 'entity-schema-definition.d.ts'), syntheticEntitySchema);

        expect(setupExtensionTooling({ projectRoot, administrationRoot }).manifest.entitySchemaAvailable).toBe(true);
    });

    it('never overwrites user-owned files and prints integration instructions instead', () => {
        writeDefaultFixtures(projectRoot);
        writeFile(path.join(projectRoot, 'tsconfig.json'), '{"compilerOptions":{"strict":false}}\n');
        writeFile(path.join(projectRoot, '.vscode/settings.json'), '{"editor.tabSize": 2}\n');

        const result = setupExtensionTooling({ projectRoot, administrationRoot });

        expect(fs.readFileSync(path.join(projectRoot, 'tsconfig.json'), 'utf8')).toContain('"strict":false');
        expect(fs.readFileSync(path.join(projectRoot, '.vscode/settings.json'), 'utf8')).toContain('editor.tabSize');
        expect(result.manifest.rootConfigs.tsconfig).toBe('conflict');
        expect(result.manifest.ideBootstraps['.vscode/settings.json']).toBe('skipped');
        expect(result.instructions.join('\n')).toContain('includes');
        expect(result.instructions.join('\n')).toContain('js/ts.tsdk.path');
        // `typescript.tsdk` is deprecated — VS Code flags it on the line we told
        // the reader to add.
        expect(result.instructions.join('\n')).not.toContain('"typescript.tsdk"');
        // The command commonly runs inside a container, where an absolute path
        // is not cmd+clickable in the editor. Every file an instruction names is
        // project-relative — asserted across the whole set, since this test
        // triggers both the root-config and the IDE-bootstrap conflict.
        expect(result.instructions.join('\n')).toContain('.vscode/settings.json is user-owned');
        expect(result.instructions.join('\n')).toContain('tsconfig.json exists and is not managed');
        expect(result.instructions.join('\n')).not.toContain(projectRoot);
    });

    it('prints the IDE settings hint as a paste-ready JSON fragment', () => {
        writeDefaultFixtures(projectRoot);
        writeFile(path.join(projectRoot, '.vscode/settings.json'), '{"editor.tabSize": 2}\n');

        const result = setupExtensionTooling({ projectRoot, administrationRoot });
        const hint = result.instructions.find((instruction) => instruction.includes('.vscode/settings.json')) ?? '';
        const entries = hint.split('\n').filter((line) => line.trim().startsWith('"'));

        expect(entries.length).toBeGreaterThan(1);
        // Every entry but the last carries its separator, so the block drops
        // straight into an existing settings object.
        expect(entries.slice(0, -1).every((line) => line.endsWith(','))).toBe(true);
        expect(entries[entries.length - 1]).not.toMatch(/,$/);
        expect(() => {
            JSON.parse(`{${entries.join('\n')}}`);
        }).not.toThrow();
    });

    it('rewrites marker-owned files when their content is outdated', () => {
        writeDefaultFixtures(projectRoot);
        writeFile(path.join(projectRoot, 'tsconfig.json'), `// ${GENERATED_MARKER}\n{"files":[]}\n`);

        const result = setupExtensionTooling({ projectRoot, administrationRoot });

        expect(result.writes.find((write) => write.file === 'tsconfig.json')?.state).toBe('updated');
        expect(readRootTsconfig().extends).toContain('tsconfig.base.json');
    });

    it('is idempotent: a second run changes nothing', () => {
        writeDefaultFixtures(projectRoot);
        setupExtensionTooling({ projectRoot, administrationRoot });

        const secondRun = setupExtensionTooling({ projectRoot, administrationRoot });

        expect(secondRun.changed).toBe(false);
        expect(secondRun.writes.filter((write) => write.state === 'created' || write.state === 'updated')).toEqual([]);
        expect(secondRun.staleFiles).toEqual([]);
    });

    it('supports --check as a validate-only mode that writes nothing', () => {
        writeDefaultFixtures(projectRoot);
        setupExtensionTooling({ projectRoot, administrationRoot });
        fs.rmSync(path.join(projectRoot, 'eslint.config.mjs'));

        const checkRun = setupExtensionTooling({ projectRoot, administrationRoot, checkOnly: true });

        expect(checkRun.changed).toBe(true);
        expect(fs.existsSync(path.join(projectRoot, 'eslint.config.mjs'))).toBe(false);
    });
});
