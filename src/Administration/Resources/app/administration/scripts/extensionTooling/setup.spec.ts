/**
 * @sw-package framework
 */

import { execFileSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { runSetupCli, setupExtensionTooling } from './setup';
import { probeCacheKey, probeInputFiles, readProbeCache, writeProbeCache } from './probe';
import { GENERATED_MARKER } from './shared';
import {
    cleanupTempProject,
    createSkeletonAdmin,
    createTempProject,
    syntheticEntitySchema,
    writeFile,
    writePluginsConfig,
    writeZeroConfigPlugin,
} from './test-helpers';

describe('scripts/extensionTooling/setup', () => {
    let projectRoot: string;
    let administrationRoot: string;

    function writeDefaultFixtures(): string {
        writeZeroConfigPlugin({ projectRoot, pluginPath: 'custom/plugins/ZeroConfig' });

        // Vendor extension with its own configs next to the admin sources.
        writeFile(path.join(projectRoot, 'vendor/acme/custom-admin/composer.json'), '{}\n');
        writeFile(path.join(projectRoot, 'vendor/acme/custom-admin/src/Resources/app/administration/src/main.ts'), [
            'export {};',
        ]);
        writeFile(path.join(projectRoot, 'vendor/acme/custom-admin/src/Resources/app/administration/tsconfig.json'), [
            '{}',
        ]);
        writeFile(path.join(projectRoot, 'vendor/acme/custom-admin/src/Resources/app/administration/eslint.config.mjs'), [
            'export default [];',
        ]);

        // Multi-bundle suite sharing one composer root.
        writeFile(path.join(projectRoot, 'custom/plugins/Suite/composer.json'), '{}\n');

        for (const bundleName of [
            'BundleA',
            'BundleB',
        ]) {
            writeFile(
                path.join(projectRoot, 'custom/plugins/Suite/src', bundleName, 'Resources/app/administration/src/main.ts'),
                ['export {};'],
            );
        }

        return writePluginsConfig(projectRoot, [
            {
                technicalName: 'ZeroConfig',
                basePath: 'custom/plugins/ZeroConfig/src',
                administrationPath: 'Resources/app/administration/src',
            },
            {
                technicalName: 'CustomAdmin',
                basePath: 'vendor/acme/custom-admin/src',
                administrationPath: 'Resources/app/administration/src',
            },
            {
                technicalName: 'SuiteA',
                basePath: 'custom/plugins/Suite/src/BundleA',
                administrationPath: 'Resources/app/administration/src',
            },
            {
                technicalName: 'SuiteB',
                basePath: 'custom/plugins/Suite/src/BundleB',
                administrationPath: 'Resources/app/administration/src',
            },
            {
                technicalName: 'administration',
                basePath: 'vendor/shopware/administration',
                administrationPath: 'Resources/app/administration/src',
            },
            { technicalName: 'MissingOnDisk', basePath: 'custom/plugins/MissingOnDisk', administrationPath: 'src' },
        ]);
    }

    beforeEach(() => {
        projectRoot = createTempProject('sw-tooling-setup-');
        administrationRoot = createSkeletonAdmin(projectRoot);
        fs.mkdirSync(path.join(administrationRoot, 'src'), { recursive: true });
        writeFile(path.join(administrationRoot, 'src', 'entity-schema-definition.d.ts'), syntheticEntitySchema);
    });

    afterEach(() => {
        cleanupTempProject(projectRoot);
    });

    it('discovers extensions, groups suites by composer root, and records modes in the manifest', () => {
        writeDefaultFixtures();

        const result = setupExtensionTooling({ projectRoot, administrationRoot });
        const names = result.manifest.projects.map((project) => project.name);

        expect(names).toEqual([
            'Suite',
            'ZeroConfig',
            'custom-admin',
        ]);

        const suite = result.manifest.projects.find((project) => project.name === 'Suite');
        const zeroConfig = result.manifest.projects.find((project) => project.name === 'ZeroConfig');
        const vendorExtension = result.manifest.projects.find((project) => project.name === 'custom-admin');

        expect(suite).toMatchObject({
            technicalNames: [
                'SuiteA',
                'SuiteB',
            ],
            vendor: false,
            ts: { mode: 'managed', verified: true },
            eslint: { mode: 'managed', verified: true },
        });
        expect(suite?.sourcePaths).toHaveLength(2);
        expect(zeroConfig).toMatchObject({ vendor: false, ts: { mode: 'managed' }, eslint: { mode: 'managed' } });
        // The vendor fixture's own configs do not compose the preset — static
        // analysis already classifies them, unverified until a check run.
        expect(vendorExtension).toMatchObject({
            vendor: true,
            ts: { mode: 'unmanaged', reason: 'not-extending', verified: false },
            eslint: { mode: 'unmanaged', reason: 'factory-not-composed', verified: false },
            tsconfig: 'vendor/acme/custom-admin/src/Resources/app/administration/tsconfig.json',
        });
        expect(result.manifest.entitySchemaAvailable).toBe(true);
    });

    it('projects root configs that mirror the generated leaf configs', () => {
        writeDefaultFixtures();

        const result = setupExtensionTooling({ projectRoot, administrationRoot });
        const rootTsconfig = fs.readFileSync(path.join(projectRoot, 'tsconfig.json'), 'utf8');
        const references = [...rootTsconfig.matchAll(/"path": "(.+)"/g)].map((match) => match[1]);
        const managedProjects = result.manifest.projects.filter((project) => project.ts.mode === 'managed');

        expect(rootTsconfig).toContain(GENERATED_MARKER);
        expect(references).toEqual(managedProjects.map((project) => `./${project.checkTsconfig}`));

        for (const reference of references) {
            expect(fs.existsSync(path.join(projectRoot, reference))).toBe(true);
        }

        const rootEslint = fs.readFileSync(path.join(projectRoot, 'eslint.config.mjs'), 'utf8');

        for (const project of result.manifest.projects) {
            for (const sourcePath of project.sourcePaths) {
                expect(rootEslint).toContain(`${JSON.stringify(sourcePath)},`);
            }
        }

        const leafFiles = fs.readdirSync(path.join(projectRoot, 'var/admin-extension-tooling/projects'));

        expect(leafFiles).toHaveLength(result.manifest.projects.length);
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
        const sourcePath = result.manifest.projects.flatMap((project) => project.sourcePaths)[0];

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

    it('never overwrites user-owned files and prints integration instructions instead', () => {
        writeDefaultFixtures();
        writeFile(path.join(projectRoot, 'tsconfig.json'), '{"compilerOptions":{"strict":false}}\n');
        writeFile(path.join(projectRoot, '.vscode/settings.json'), '{"editor.tabSize": 2}\n');

        const result = setupExtensionTooling({ projectRoot, administrationRoot });

        expect(fs.readFileSync(path.join(projectRoot, 'tsconfig.json'), 'utf8')).toContain('"strict":false');
        expect(fs.readFileSync(path.join(projectRoot, '.vscode/settings.json'), 'utf8')).toContain('editor.tabSize');
        expect(result.manifest.rootConfigs.tsconfig).toBe('conflict');
        expect(result.manifest.ideBootstraps['.vscode/settings.json']).toBe('skipped');
        expect(result.instructions.join('\n')).toContain('references');
        expect(result.instructions.join('\n')).toContain('typescript.tsdk');
    });

    it('rewrites marker-owned files when their content is outdated', () => {
        writeDefaultFixtures();
        writeFile(path.join(projectRoot, 'tsconfig.json'), `// ${GENERATED_MARKER}\n{"files":[],"references":[]}\n`);

        const result = setupExtensionTooling({ projectRoot, administrationRoot });
        const rootWrite = result.writes.find((write) => write.file === path.join(projectRoot, 'tsconfig.json'));

        expect(rootWrite?.state).toBe('updated');
        expect(fs.readFileSync(path.join(projectRoot, 'tsconfig.json'), 'utf8')).toContain('admin-extension-tooling');
    });

    it('is idempotent: a second run changes nothing', () => {
        writeDefaultFixtures();
        setupExtensionTooling({ projectRoot, administrationRoot });

        const secondRun = setupExtensionTooling({ projectRoot, administrationRoot });

        expect(secondRun.changed).toBe(false);
        expect(secondRun.writes.filter((write) => write.state === 'created' || write.state === 'updated')).toEqual([]);
        expect(secondRun.staleFiles).toEqual([]);
    });

    it('supports --check as a validate-only mode that writes nothing', () => {
        writeDefaultFixtures();
        setupExtensionTooling({ projectRoot, administrationRoot });
        fs.rmSync(path.join(projectRoot, 'eslint.config.mjs'));

        const checkRun = setupExtensionTooling({ projectRoot, administrationRoot, checkOnly: true });

        expect(checkRun.changed).toBe(true);
        expect(fs.existsSync(path.join(projectRoot, 'eslint.config.mjs'))).toBe(false);
    });

    it('writes an entity schema stub that keeps the entity list empty and reports unavailability', () => {
        writeDefaultFixtures();
        fs.rmSync(path.join(administrationRoot, 'src', 'entity-schema-definition.d.ts'));

        const result = setupExtensionTooling({ projectRoot, administrationRoot });
        const stub = fs.readFileSync(path.join(administrationRoot, 'src', 'entity-schema-definition.d.ts'), 'utf8');

        expect(result.manifest.entitySchemaAvailable).toBe(false);
        expect(stub).toContain(GENERATED_MARKER);
        expect(stub).toContain('interface Entities {}');
        expect(result.warnings.join('\n')).toContain('admin:generate-entity-schema-types');

        // The real generated schema is never treated as a stub.
        writeFile(path.join(administrationRoot, 'src', 'entity-schema-definition.d.ts'), syntheticEntitySchema);

        const withRealSchema = setupExtensionTooling({ projectRoot, administrationRoot });

        expect(withRealSchema.manifest.entitySchemaAvailable).toBe(true);
    });

    it('removes stale leaf configs when an extension disappears', () => {
        writeDefaultFixtures();
        setupExtensionTooling({ projectRoot, administrationRoot });

        writePluginsConfig(projectRoot, [
            {
                technicalName: 'ZeroConfig',
                basePath: 'custom/plugins/ZeroConfig/src',
                administrationPath: 'Resources/app/administration/src',
            },
        ]);

        const result = setupExtensionTooling({ projectRoot, administrationRoot });
        const leafFiles = fs.readdirSync(path.join(projectRoot, 'var/admin-extension-tooling/projects'));

        expect(result.staleFiles.length).toBeGreaterThan(0);
        expect(leafFiles).toEqual(['zeroconfig.json']);
    });

    it('generates self-ignoring shims only below custom/plugins', () => {
        writeDefaultFixtures();

        const result = setupExtensionTooling({ projectRoot, administrationRoot, shim: 'ZeroConfig' });
        const shimDir = path.join(projectRoot, 'custom/plugins/ZeroConfig/src/Resources/app/administration/.shopware-admin');
        const shimTsconfig = fs.readFileSync(path.join(shimDir, 'tsconfig.json'), 'utf8');

        expect(fs.readFileSync(path.join(shimDir, '.gitignore'), 'utf8')).toContain('*');
        expect(shimTsconfig).toContain('tsconfig.base.json');
        expect(shimTsconfig).toContain('admin-types.d.ts');
        expect(fs.readFileSync(path.join(shimDir, 'eslint.mjs'), 'utf8')).toContain('shopwareAdminExtension');
        expect(result.writes.some((write) => write.file.includes('.shopware-admin'))).toBe(true);

        expect(() => setupExtensionTooling({ projectRoot, administrationRoot, shim: 'CustomAdmin' })).toThrow(
            /only generated below custom\/plugins/,
        );
        expect(() => setupExtensionTooling({ projectRoot, administrationRoot, shim: 'DoesNotExist' })).toThrow(
            /No extension matches/,
        );
    });

    it('scaffolds committable plugin configs that extend the bridge and are never overwritten', () => {
        writeDefaultFixtures();

        const adminFolder = path.join(projectRoot, 'custom/plugins/ZeroConfig/src/Resources/app/administration');
        setupExtensionTooling({ projectRoot, administrationRoot, shim: 'ZeroConfig' });

        const pluginTsconfig = fs.readFileSync(path.join(adminFolder, 'tsconfig.json'), 'utf8');
        const pluginEslint = fs.readFileSync(path.join(adminFolder, 'eslint.config.mjs'), 'utf8');

        // Committable: extends/imports the bridge, carries no generated marker.
        expect(pluginTsconfig).toContain('"extends": "./.shopware-admin/tsconfig.json"');
        expect(pluginTsconfig).not.toContain(GENERATED_MARKER);
        expect(pluginEslint).toContain("import shopware from './.shopware-admin/eslint.mjs'");

        // A developer edit survives a re-run, and the extension is discovered as bridged.
        fs.appendFileSync(path.join(adminFolder, 'eslint.config.mjs'), '// my custom rule\n');
        const rerun = setupExtensionTooling({ projectRoot, administrationRoot, shim: 'ZeroConfig' });

        expect(fs.readFileSync(path.join(adminFolder, 'eslint.config.mjs'), 'utf8')).toContain('// my custom rule');
        expect(rerun.manifest.projects.find((project) => project.name === 'ZeroConfig')?.bridgePresent).toBe(true);
    });

    it('never overwrites an existing plugin config and warns how to add the extends', () => {
        writeDefaultFixtures();

        const adminFolder = path.join(projectRoot, 'custom/plugins/ZeroConfig/src/Resources/app/administration');

        writeFile(path.join(adminFolder, 'tsconfig.json'), ['{ "compilerOptions": { "strict": true } }']);

        const result = setupExtensionTooling({ projectRoot, administrationRoot, shim: 'ZeroConfig' });

        expect(fs.readFileSync(path.join(adminFolder, 'tsconfig.json'), 'utf8')).toContain('"strict": true');
        expect(result.warnings.join('\n')).toContain('extends');
    });

    it('merges plugin aliases and preset host paths into the shim tsconfig', () => {
        writeDefaultFixtures();
        writeFile(
            path.join(projectRoot, 'custom/plugins/ZeroConfig/src/Resources/app/administration/tsconfig.aliases.json'),
            `${JSON.stringify({ 'ZeroConfig/*': ['src/*'] })}\n`,
        );
        setupExtensionTooling({ projectRoot, administrationRoot, shim: 'ZeroConfig' });

        const shimTsconfig = fs.readFileSync(
            path.join(
                projectRoot,
                'custom/plugins/ZeroConfig/src/Resources/app/administration/.shopware-admin/tsconfig.json',
            ),
            'utf8',
        );
        const parsed = JSON.parse(shimTsconfig.split('\n').slice(1).join('\n')) as {
            compilerOptions: { paths: Record<string, string[]> };
        };

        expect(parsed.compilerOptions.paths['ZeroConfig/*']).toEqual(['../src/*']);
        expect(parsed.compilerOptions.paths.vue[0]).toContain('node_modules/vue');
    });

    describe('probe cache integration', () => {
        it('adopts cached verified verdicts while inputs match and prunes removed extensions', () => {
            writeFile(path.join(projectRoot, 'custom/plugins/Probe/composer.json'), '{}\n');
            writeFile(path.join(projectRoot, 'custom/plugins/Probe/src/Resources/app/administration/src/main.ts'), [
                'export {};',
            ]);
            writeFile(path.join(projectRoot, 'custom/plugins/Probe/src/Resources/app/administration/tsconfig.json'), [
                '{ "compilerOptions": { "strict": true } }',
            ]);
            writePluginsConfig(projectRoot, [
                {
                    technicalName: 'Probe',
                    basePath: 'custom/plugins/Probe/src',
                    administrationPath: 'Resources/app/administration/src',
                },
            ]);

            const staticRun = setupExtensionTooling({ projectRoot, administrationRoot });

            expect(staticRun.manifest.projects[0].ts).toEqual({
                mode: 'unmanaged',
                reason: 'not-extending',
                verified: false,
            });

            const inputs = probeInputFiles(staticRun.manifest.projects[0], projectRoot, administrationRoot);

            writeProbeCache(projectRoot, {
                version: 1,
                entries: {
                    Probe: {
                        ts: {
                            key: probeCacheKey(inputs.ts),
                            resolution: { mode: 'unmanaged', reason: 'surface-not-injected', verified: true },
                        },
                    },
                    Ghost: {
                        ts: { key: 'stale', resolution: { mode: 'custom', verified: true } },
                    },
                },
            });

            const cachedRun = setupExtensionTooling({ projectRoot, administrationRoot });

            expect(cachedRun.manifest.projects[0].ts).toEqual({
                mode: 'unmanaged',
                reason: 'surface-not-injected',
                verified: true,
            });
            expect(readProbeCache(projectRoot)?.entries.Ghost).toBeUndefined();

            // A config edit invalidates the hash — back to the static verdict.
            writeFile(path.join(projectRoot, 'custom/plugins/Probe/src/Resources/app/administration/tsconfig.json'), [
                '{ "compilerOptions": { "strict": false } }',
            ]);

            const invalidatedRun = setupExtensionTooling({ projectRoot, administrationRoot });

            expect(invalidatedRun.manifest.projects[0].ts.verified).toBe(false);
        });
    });

    describe('runSetupCli', () => {
        function listTree(root: string): string[] {
            return (fs.readdirSync(root, { recursive: true }) as string[]).sort();
        }

        it('rejects a mistyped flag with exit 2 and writes nothing', () => {
            writeDefaultFixtures();

            const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});
            const treeBefore = listTree(projectRoot);

            const exitCode = runSetupCli([
                '--chekc',
                `--project-root=${projectRoot}`,
                `--administration-root=${administrationRoot}`,
            ]);

            expect(exitCode).toBe(2);
            expect(listTree(projectRoot)).toEqual(treeBefore);
            expect(errorSpy.mock.calls.join('\n')).toContain('Did you mean --check?');
            errorSpy.mockRestore();
        });

        it('prints help with exit 0 before resolving PROJECT_ROOT and writes nothing', () => {
            const previousProjectRoot = process.env.PROJECT_ROOT;
            delete process.env.PROJECT_ROOT;

            const logSpy = jest.spyOn(console, 'log').mockImplementation(() => {});
            const treeBefore = listTree(projectRoot);

            const exitCode = runSetupCli(['--help']);

            expect(exitCode).toBe(0);
            expect(listTree(projectRoot)).toEqual(treeBefore);
            expect(logSpy.mock.calls.join('\n')).toContain('composer admin:setup-extension-tooling -- [options]');
            logSpy.mockRestore();

            if (previousProjectRoot !== undefined) {
                process.env.PROJECT_ROOT = previousProjectRoot;
            }
        });

        it('treats a missing PROJECT_ROOT as a usage error with exit 2', () => {
            const previousProjectRoot = process.env.PROJECT_ROOT;
            delete process.env.PROJECT_ROOT;

            const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

            const exitCode = runSetupCli(['--check']);

            expect(exitCode).toBe(2);
            expect(errorSpy.mock.calls.join('\n')).toContain('PROJECT_ROOT or --project-root is required.');
            errorSpy.mockRestore();

            if (previousProjectRoot !== undefined) {
                process.env.PROJECT_ROOT = previousProjectRoot;
            }
        });
    });

    it('generates shims for every writable extension with --shim=all-custom', () => {
        writeDefaultFixtures();
        setupExtensionTooling({ projectRoot, administrationRoot, shim: 'all-custom' });

        expect(
            fs.existsSync(
                path.join(projectRoot, 'custom/plugins/ZeroConfig/src/Resources/app/administration/.shopware-admin'),
            ),
        ).toBe(true);
        expect(
            fs.existsSync(
                path.join(projectRoot, 'custom/plugins/Suite/src/BundleA/Resources/app/administration/.shopware-admin'),
            ),
        ).toBe(true);
        expect(
            fs.existsSync(
                path.join(projectRoot, 'vendor/acme/custom-admin/src/Resources/app/administration/.shopware-admin'),
            ),
        ).toBe(false);
    });
});
