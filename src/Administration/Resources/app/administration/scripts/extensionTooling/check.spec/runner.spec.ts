/**
 * @sw-package framework
 *
 * Integration: the runner drives the Administration's real `tsc` and `eslint`
 * against a fixture extension. The Administration root is a stub carrying the
 * real presets and the real binaries, so the specs exercise the whole pipeline
 * without pulling the host's thousands of source files into every program.
 */

import { spawnSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { discoverAdminRoots } from '../discovery';
import { EXIT_FINDINGS, EXIT_OK, EXIT_TOOL_ERROR, exitCodeFor, zeroFileErrors } from '../report';
import { collectExtensionPaths, runCheck, writeProgram } from '../runner';
import {
    createExtension,
    createStubAdministration,
    createTempProject,
    removeTempProject,
    writeBundleConfig,
    writeTree,
} from '../test-helpers';
import type { AdminRoot, CheckReport, Finding } from '../shared';

jest.setTimeout(300_000);

const TYPE_ERROR_AND_LINT_VIOLATION = `export const label: number = Shopware.name;

const unusedValue = 'never read';
`;

function findingsOf(report: CheckReport, tool: 'types' | 'lint'): Finding[] {
    return report.roots.flatMap((rootReport) =>
        rootReport.runs.filter((run) => run.tool === tool).flatMap((run) => run.findings),
    );
}

function filesChecked(report: CheckReport, tool: 'types' | 'lint'): number {
    return report.roots
        .flatMap((rootReport) => rootReport.runs.filter((run) => run.tool === tool))
        .reduce((total, run) => total + run.filesChecked, 0);
}

describe('scripts/extensionTooling/runner (integration)', () => {
    let projectRoot: string;
    let administrationRoot: string;

    beforeEach(() => {
        projectRoot = createTempProject();
        administrationRoot = createStubAdministration(projectRoot);
    });

    afterEach(() => {
        removeTempProject(projectRoot);
    });

    const discover = (): AdminRoot[] =>
        discoverAdminRoots({
            projectRoot,
            administrationRoot,
            pluginsConfigPath: path.join(projectRoot, 'var', 'plugins.json'),
        });

    const check = (overrides: { types?: boolean; lint?: boolean; fix?: boolean; roots?: AdminRoot[] } = {}): CheckReport =>
        runCheck({
            projectRoot,
            administrationRoot,
            roots: overrides.roots ?? discover(),
            types: overrides.types ?? true,
            lint: overrides.lint ?? true,
            fix: overrides.fix ?? false,
        });

    describe('a fixture extension with one type error and one lint violation', () => {
        beforeEach(() => {
            const extension = createExtension(projectRoot, {
                name: 'SwagFixture',
                sources: { 'main.ts': TYPE_ERROR_AND_LINT_VIOLATION },
            });

            writeBundleConfig(projectRoot, {
                SwagFixture: { basePath: extension.basePath, technicalName: 'swag-fixture' },
            });
        });

        it('reports the type error against the installed type surface', () => {
            const report = check();

            expect(findingsOf(report, 'types')).toEqual(
                expect.arrayContaining([
                    expect.objectContaining({
                        file: 'custom/plugins/SwagFixture/src/Resources/app/administration/src/main.ts',
                        rule: 'TS2322',
                        severity: 'error',
                    }),
                ]),
            );
        });

        it('reports the lint violation', () => {
            const report = check();

            expect(findingsOf(report, 'lint')).toEqual(
                expect.arrayContaining([
                    expect.objectContaining({
                        file: 'custom/plugins/SwagFixture/src/Resources/app/administration/src/main.ts',
                        rule: '@typescript-eslint/no-unused-vars',
                        severity: 'error',
                    }),
                ]),
            );
        });

        it('counts the files both tools actually saw and exits 1', () => {
            const report = check();

            expect(filesChecked(report, 'types')).toBe(1);
            expect(filesChecked(report, 'lint')).toBe(1);
            expect(zeroFileErrors(report)).toEqual([]);
            expect(exitCodeFor(report)).toBe(EXIT_FINDINGS);
        });

        it('writes nothing into the extension', () => {
            const adminFolder = path.join(projectRoot, 'custom/plugins/SwagFixture/src/Resources/app/administration');

            check();

            expect(fs.readdirSync(adminFolder)).toEqual(['src']);
            expect(fs.readdirSync(path.join(adminFolder, 'src'))).toEqual(['main.ts']);
            expect(fs.readFileSync(path.join(adminFolder, 'src/main.ts'), 'utf8')).toBe(TYPE_ERROR_AND_LINT_VIOLATION);
        });

        it('generates exactly one program directory per source root below var/', () => {
            check();

            const toolingDir = path.join(projectRoot, 'var/admin-extension-tooling');

            expect(fs.readdirSync(toolingDir)).toEqual(['swag-fixture']);
            expect(fs.readdirSync(path.join(toolingDir, 'swag-fixture')).sort()).toEqual([
                'eslint.config.mjs',
                'tsconfig.json',
            ]);
        });

        it('runs only the requested tool', () => {
            const typesOnly = check({ lint: false });

            expect(typesOnly.roots[0].runs.map((run) => run.tool)).toEqual(['types']);

            const lintOnly = check({ types: false });

            expect(lintOnly.roots[0].runs.map((run) => run.tool)).toEqual(['lint']);
        });
    });

    it('reports a clean extension as clean', () => {
        const extension = createExtension(projectRoot, {
            name: 'SwagClean',
            sources: { 'main.ts': 'export const label: string = Shopware.name;\n' },
        });

        writeBundleConfig(projectRoot, {
            SwagClean: { basePath: extension.basePath, technicalName: 'swag-clean' },
        });

        const report = check();

        expect(findingsOf(report, 'types')).toEqual([]);
        expect(findingsOf(report, 'lint').filter((finding) => finding.severity === 'error')).toEqual([]);
        expect(exitCodeFor(report)).toBe(EXIT_OK);
    });

    describe('zero files checked', () => {
        it('is a tool error, not a clean run (A9)', () => {
            const extension = createExtension(projectRoot, {
                name: 'SwagNoSources',
                sources: { 'notes.md': '# nothing to check here\n' },
            });

            writeBundleConfig(projectRoot, {
                SwagNoSources: { basePath: extension.basePath, technicalName: 'swag-no-sources' },
            });

            const report = check();

            expect(filesChecked(report, 'types')).toBe(0);
            expect(filesChecked(report, 'lint')).toBe(0);
            expect(zeroFileErrors(report)).toEqual([
                'Checked 0 files for SwagNoSources (types) — this is a tool error, not a clean result.',
                'Checked 0 files for SwagNoSources (lint) — this is a tool error, not a clean result.',
            ]);
            expect(exitCodeFor(report)).toBe(EXIT_TOOL_ERROR);
        });

        it('is what a wrong working directory produces — ESLint exits 0 having linted nothing', () => {
            const extension = createExtension(projectRoot, {
                name: 'SwagWrongCwd',
                sources: { 'main.ts': 'export const label: string = Shopware.name;\n' },
            });

            writeBundleConfig(projectRoot, {
                SwagWrongCwd: { basePath: extension.basePath, technicalName: 'swag-wrong-cwd' },
            });

            const roots = discover();
            const program = writeProgram(roots[0], projectRoot, administrationRoot);
            const eslintBinary = path.join(administrationRoot, 'node_modules/.bin/eslint');
            const wrongCwd = path.join(projectRoot, 'var');

            // Reproduces the trap: with an explicit --config, ESLint derives its base
            // path from the process cwd. Spawned from anywhere but the project root it
            // treats every target as ignored and exits 0 with an empty report.
            const result = spawnSync(
                eslintBinary,
                [
                    '--config',
                    program.eslintConfigPath,
                    '--format',
                    'json',
                    '--no-warn-ignored',
                    '--no-error-on-unmatched-pattern',
                    path.relative(projectRoot, roots[0].sourcePath),
                ],
                { cwd: wrongCwd, encoding: 'utf8' },
            );

            expect(result.status).toBe(0);
            expect(JSON.parse(result.stdout)).toEqual([]);

            // …which is precisely why the runner spawns with cwd = projectRoot and why
            // zero files checked can never be reported as a clean result.
            expect(exitCodeFor(check({ roots }))).toBe(EXIT_OK);
        });

        it('spawns every tool with the project root as working directory', () => {
            const extension = createExtension(projectRoot, {
                name: 'SwagSpawn',
                sources: { 'main.ts': 'export const label: string = Shopware.name;\n' },
            });

            writeBundleConfig(projectRoot, {
                SwagSpawn: { basePath: extension.basePath, technicalName: 'swag-spawn' },
            });

            const spawned: { command: string; cwd: string }[] = [];

            runCheck({
                projectRoot,
                administrationRoot,
                roots: discover(),
                types: true,
                lint: true,
                fix: false,
                spawn: (command, args, options) => {
                    spawned.push({ command, cwd: options.cwd });

                    return { status: 0, stdout: command.endsWith('eslint') ? '[]' : '', stderr: '' };
                },
            });

            expect(spawned).toHaveLength(2);
            expect(spawned.every((call) => call.cwd === projectRoot)).toBe(true);
        });
    });

    it('counts diagnostics in the host sources instead of blaming the extension for them', () => {
        // The Administration's own sources enter every extension program through
        // admin-types.d.ts, and they are not type-clean under the extension preset's
        // compiler options. An extension author cannot act on those, so they must not
        // appear as findings and must not decide the exit code.
        writeTree(administrationRoot, {
            'extension-tooling/admin-types.d.ts':
                "import '../src/host';\n\ndeclare global {\n    const Shopware: { name: string };\n}\n",
            'src/host.ts': "export const broken: number = 'not a number';\n",
        });

        const extension = createExtension(projectRoot, {
            name: 'SwagInnocent',
            sources: { 'main.ts': 'export const label: string = Shopware.name;\n' },
        });

        writeBundleConfig(projectRoot, {
            SwagInnocent: { basePath: extension.basePath, technicalName: 'swag-innocent' },
        });

        const report = check();
        const typeRun = report.roots[0].runs.find((run) => run.tool === 'types');

        expect(typeRun?.findings).toEqual([]);
        expect(typeRun?.externalFindings).toBe(1);
        expect(exitCodeFor(report)).toBe(EXIT_OK);
    });

    describe('extension-owned paths', () => {
        it("copies the extension's own paths into the generated program, resolved absolutely", () => {
            const extension = createExtension(projectRoot, {
                name: 'SwagAliased',
                sources: {
                    'main.ts': "import { helper } from '@swag/helper';\n\nexport const label: string = helper;\n",
                    'lib/helper.ts': "export const helper = 'help';\n",
                },
                tsconfig: JSON.stringify(
                    {
                        // A committed plugin config may set baseUrl; the runner re-anchors
                        // the mappings itself instead of inheriting that baseUrl.
                        compilerOptions: { baseUrl: 'src', paths: { '@swag/helper': ['lib/helper.ts'] } },
                    },
                    null,
                    4,
                ),
            });

            writeBundleConfig(projectRoot, {
                SwagAliased: { basePath: extension.basePath, technicalName: 'swag-aliased' },
            });

            const roots = discover();
            const expectedTarget = path.join(
                projectRoot,
                'custom/plugins/SwagAliased/src/Resources/app/administration/src/lib/helper.ts',
            );

            expect(collectExtensionPaths(roots[0])).toEqual({ '@swag/helper': [expectedTarget] });
            expect(findingsOf(check({ roots }), 'types')).toEqual([]);
        });

        it('still maps the host resolution when the extension declares no paths', () => {
            const extension = createExtension(projectRoot, {
                name: 'SwagNoPaths',
                sources: { 'main.ts': 'export const label: string = Shopware.name;\n' },
            });

            writeBundleConfig(projectRoot, {
                SwagNoPaths: { basePath: extension.basePath, technicalName: 'swag-no-paths' },
            });

            const roots = discover();

            expect(collectExtensionPaths(roots[0])).toEqual({});

            const program = writeProgram(roots[0], projectRoot, administrationRoot);
            const generated = JSON.parse(fs.readFileSync(program.tsconfigPath, 'utf8')) as {
                extends: string;
                files: string[];
                include: string[];
                compilerOptions: { paths: Record<string, string[]> };
            };

            // The host mappings are never absent: without them the Administration's
            // own sources cannot resolve each other and the type surface dies.
            expect(generated.compilerOptions.paths).toEqual({
                '*': [
                    `${administrationRoot}/*`,
                    `${administrationRoot}/node_modules/*`,
                ],
            });
            expect(generated.extends).toBe(path.join(administrationRoot, 'extension-tooling/tsconfig.base.json'));
            expect(generated.files).toEqual([path.join(administrationRoot, 'extension-tooling/admin-types.d.ts')]);
            expect(generated.include).toEqual([`${roots[0].sourcePath}/**/*`]);
        });

        it('lets the extension override the host wildcard', () => {
            const extension = createExtension(projectRoot, {
                name: 'SwagOwnWildcard',
                sources: { 'main.ts': 'export const label: string = Shopware.name;\n' },
                tsconfig: JSON.stringify({ compilerOptions: { paths: { '*': ['./vendor-types/*'] } } }, null, 4),
            });

            writeBundleConfig(projectRoot, {
                SwagOwnWildcard: { basePath: extension.basePath, technicalName: 'swag-own-wildcard' },
            });

            const roots = discover();
            const program = writeProgram(roots[0], projectRoot, administrationRoot);
            const generated = JSON.parse(fs.readFileSync(program.tsconfigPath, 'utf8')) as {
                compilerOptions: { paths: Record<string, string[]> };
            };

            expect(generated.compilerOptions.paths['*']).toEqual([`${roots[0].adminFolder}/vendor-types/*`]);
        });

        it('ignores an unreadable extension tsconfig instead of failing the run', () => {
            const extension = createExtension(projectRoot, {
                name: 'SwagBrokenConfig',
                sources: { 'main.ts': 'export const label: string = Shopware.name;\n' },
                tsconfig: '{ not json at all',
            });

            writeBundleConfig(projectRoot, {
                SwagBrokenConfig: { basePath: extension.basePath, technicalName: 'swag-broken-config' },
            });

            const roots = discover();

            expect(collectExtensionPaths(roots[0])).toEqual({});
            expect(exitCodeFor(check({ roots }))).toBe(EXIT_OK);
        });
    });

    describe('missing toolchain', () => {
        it('names the missing binary and the npm ci fix', () => {
            fs.unlinkSync(path.join(administrationRoot, 'node_modules'));
            writeTree(projectRoot, { 'admin/node_modules/.gitkeep': '' });

            const report = runCheck({
                projectRoot,
                administrationRoot,
                roots: [],
                types: true,
                lint: true,
                fix: false,
            });

            expect(report.errors[0]).toContain('Administration dependencies are missing');
            expect(report.errors[1]).toContain('Run "npm ci" in admin');
            expect(exitCodeFor(report)).toBe(EXIT_TOOL_ERROR);
        });
    });
});
