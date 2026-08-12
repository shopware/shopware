/**
 * @sw-package framework
 *
 * Orchestration coverage for checkExtensions with the child-process spawn
 * point replaced by a counting fake: proves that --max-workers bounds the
 * concurrent tool processes across every fan-out (coverage probes, vue-tsc
 * programs, spec programs, ESLint groups) and that reported durations are the
 * stage's wall-clock time, not the sum of concurrent process times. Probe
 * calls inside probe.ts keep the real runCommand (module-internal binding),
 * which the fixtures avoid via zero-config targets.
 */

import fs from 'fs';
import path from 'path';
import { runCommand } from '../probe-command';
import { checkExtensions } from '../check';
import { listSpecFiles, listTypeCheckableFiles } from '../check-parsing';
import {
    cleanupTempProject,
    createSkeletonAdmin,
    createTempProject,
    realAdministrationRoot,
    syntheticEntitySchema,
    writeFile,
    writePluginsConfig,
} from '../test-helpers';

jest.mock('../probe-command', () => ({
    ...jest.requireActual<Record<string, unknown>>('../probe-command'),
    runCommand: jest.fn(),
}));

const runCommandMock = runCommand as jest.MockedFunction<typeof runCommand>;

function linkRealToolchain(administrationRoot: string): void {
    fs.rmSync(path.join(administrationRoot, 'node_modules'), { recursive: true, force: true });
    fs.symlinkSync(path.join(realAdministrationRoot, 'node_modules'), path.join(administrationRoot, 'node_modules'), 'dir');
}

describe('scripts/extensionTooling/check orchestration', () => {
    let projectRoot: string;
    let administrationRoot: string;
    let running: number;
    let maxRunning: number;

    /**
     * Answers like the real tools would, with an absurd per-process duration
     * (60s) that must never surface in the aggregated stage durations.
     */
    function installCountingFake(vueTscOutput: { status: number; output: string }): void {
        runCommandMock.mockImplementation(async (command, args) => {
            running += 1;
            maxRunning = Math.max(maxRunning, running);
            await new Promise((resolve) => {
                setTimeout(resolve, 15);
            });
            running -= 1;

            // The real tools print their payload on stdout; `output` is the
            // merged view the reporter renders.
            const answer = (result: { status: number; output: string; stderr?: string }) => ({
                durationMs: 60000,
                timedOut: false,
                status: result.status,
                stdout: result.output,
                stderr: result.stderr ?? '',
                output: result.stderr ? `${result.output}\n${result.stderr}`.trim() : result.output,
            });

            if (args.includes('--showConfig')) {
                // The probe resolves composition from whether the config's
                // extends chain reaches the bridge's admin-types surface; an
                // auto-bridged config extends the generated .shopware/ tsconfig.
                // The dedicated spec program carries only the spec files.
                const configPath = args[args.indexOf('--project') + 1];
                const composes = fs.readFileSync(configPath, 'utf8').includes('.shopware/tsconfig.json');
                const files = configPath.includes('tsconfig.specs.json')
                    ? listSpecFiles(projectRoot, ['custom/plugins'])
                    : listTypeCheckableFiles(projectRoot, ['custom/plugins']);

                return answer({
                    status: 0,
                    output: JSON.stringify({
                        files: composes
                            ? [
                                  ...files,
                                  'admin-types.d.ts',
                              ]
                            : files,
                    }),
                    // vue-tsc runs under node, which is free to print notices
                    // here — the JSON must still resolve.
                    stderr: '(node:1) ExperimentalWarning: some feature is experimental',
                });
            }

            if (args[0].includes('eslint')) {
                // --print-config is the composition probe; the auto-bridged
                // config carries the factory's runtime-contract rule.
                return answer({
                    status: 0,
                    output: args.includes('--print-config') ? 'plugin-rules/no-src-imports' : '',
                });
            }

            return answer(vueTscOutput);
        });
    }

    function writeZeroConfigSuite(name: string, bundles: string[]): void {
        writeFile(path.join(projectRoot, `custom/plugins/${name}/composer.json`), '{}\n');

        for (const bundle of bundles) {
            const sourceRoot = path.join(
                projectRoot,
                `custom/plugins/${name}/src/${bundle}/Resources/app/administration/src`,
            );

            writeFile(path.join(sourceRoot, 'main.ts'), ['export {};']);
            writeFile(path.join(sourceRoot, 'main.spec.ts'), ['export {};']);
        }
    }

    beforeEach(() => {
        projectRoot = createTempProject('sw-tooling-orchestration-');
        administrationRoot = createSkeletonAdmin(projectRoot);
        writeFile(path.join(administrationRoot, 'src', 'entity-schema-definition.d.ts'), syntheticEntitySchema);
        linkRealToolchain(administrationRoot);
        running = 0;
        maxRunning = 0;
        runCommandMock.mockReset();
    });

    afterEach(() => {
        cleanupTempProject(projectRoot);
    });

    it('bounds concurrent tool processes across extensions to --max-workers', async () => {
        writeZeroConfigSuite('Alfa', [
            'BundleA',
            'BundleB',
            'BundleC',
        ]);
        writeZeroConfigSuite('Bravo', [
            'BundleA',
            'BundleB',
            'BundleC',
        ]);
        writePluginsConfig(
            projectRoot,
            [
                'Alfa',
                'Bravo',
            ].flatMap((name) =>
                [
                    'BundleA',
                    'BundleB',
                    'BundleC',
                ].map((bundle) => ({
                    technicalName: `${name}${bundle}`,
                    basePath: `custom/plugins/${name}/src/${bundle}`,
                    administrationPath: 'Resources/app/administration/src',
                })),
            ),
        );
        installCountingFake({ status: 0, output: '' });

        const check = await checkExtensions({ projectRoot, administrationRoot, maxWorkers: 2 });

        expect(check.results).toHaveLength(2);
        expect(check.exitCode).toBe(0);

        for (const result of check.results) {
            expect(result.typescript.status).toBe('passed');
            expect(result.typescriptSpecs.status).toBe('passed');
            expect(result.eslint.status).toBe('passed');
        }

        // 2 extensions × (3 coverage + 3 runtime + 3 spec coverage + 3 spec
        // programs + 1 ESLint group) — enough parallel work that an unshared
        // or per-extension limit would exceed 2 concurrent processes.
        expect(runCommandMock.mock.calls.length).toBeGreaterThanOrEqual(26);
        expect(maxRunning).toBe(2);

        // Every fake process reports 60s; a stage that still summed process
        // times would report multiples of that instead of wall-clock time.
        for (const result of check.results) {
            expect(result.typescript.durationMs).toBeLessThan(60000);
            expect(result.typescriptSpecs.durationMs).toBeLessThan(60000);
            expect(result.eslint.durationMs).toBeLessThan(60000);
        }
    }, 60000);

    it('records skipped targets while the managed subset fails', async () => {
        writeFile(path.join(projectRoot, 'custom/plugins/Mixed/composer.json'), '{}\n');
        // BundleA is zero-config and fails through the faked vue-tsc program.
        writeFile(path.join(projectRoot, 'custom/plugins/Mixed/src/BundleA/Resources/app/administration/src/main.ts'), [
            'export {};',
        ]);
        // BundleB owns a non-composing tsconfig — the real probe resolves it unmanaged.
        writeFile(path.join(projectRoot, 'custom/plugins/Mixed/src/BundleB/Resources/app/administration/src/main.ts'), [
            'export {};',
        ]);
        writeFile(path.join(projectRoot, 'custom/plugins/Mixed/src/BundleB/Resources/app/administration/tsconfig.json'), [
            '{',
            '    "compilerOptions": { "strict": true, "noEmit": true },',
            '    "include": ["src/**/*.ts"]',
            '}',
        ]);
        writePluginsConfig(projectRoot, [
            {
                technicalName: 'MixedA',
                basePath: 'custom/plugins/Mixed/src/BundleA',
                administrationPath: 'Resources/app/administration/src',
            },
            {
                technicalName: 'MixedB',
                basePath: 'custom/plugins/Mixed/src/BundleB',
                administrationPath: 'Resources/app/administration/src',
            },
        ]);
        installCountingFake({
            status: 2,
            output: 'custom/plugins/Mixed/src/BundleA/Resources/app/administration/src/main.ts(1,1): error TS2322: broken',
        });

        const check = await checkExtensions({ projectRoot, administrationRoot, maxWorkers: 2 });
        const result = check.results[0];

        expect(result.typescript.status).toBe('failed');
        expect(result.skippedTargets).toHaveLength(1);
        expect(result.skippedTargets?.[0]).toMatchObject({
            tool: 'TypeScript',
            sourcePath: 'custom/plugins/Mixed/src/BundleB/Resources/app/administration/src',
            configPath: 'custom/plugins/Mixed/src/BundleB/Resources/app/administration/tsconfig.json',
        });
        expect(result.skippedTargets?.[0].detail).toContain('extends chain does not reach');
        expect(check.exitCode).toBe(1);
    }, 60000);

    it('reports a crashed vue-tsc as a tooling error even when it prints noise', async () => {
        writeZeroConfigSuite('Solo', ['BundleA']);
        writePluginsConfig(projectRoot, [
            {
                technicalName: 'SoloBundleA',
                basePath: 'custom/plugins/Solo/src/BundleA',
                administrationPath: 'Resources/app/administration/src',
            },
        ]);
        // Non-zero exit with output that carries no `error TS…` diagnostic — a
        // crash (heap OOM, panic), not a clean run. It must never read `passed`.
        installCountingFake({
            status: 1,
            output: 'FATAL ERROR: Reached heap limit Allocation failed - JavaScript heap out of memory',
        });

        const check = await checkExtensions({ projectRoot, administrationRoot });
        const result = check.results[0];

        expect(result.typescript.status).toBe('tooling-error');
        expect(result.typescript.output).toContain('heap out of memory');
        expect(check.exitCode).toBe(1);
    }, 60000);

    // The counting fake always prints a node warning to stderr alongside the
    // --showConfig JSON, so every run above already exercises this. Asserted
    // explicitly here because reading the merged output instead of stdout used
    // to fail the JSON parse and abort the whole extension.
    it('resolves coverage when --showConfig prints a warning on stderr', async () => {
        writeZeroConfigSuite('Solo', ['BundleA']);
        writePluginsConfig(projectRoot, [
            {
                technicalName: 'SoloBundleA',
                basePath: 'custom/plugins/Solo/src/BundleA',
                administrationPath: 'Resources/app/administration/src',
            },
        ]);
        installCountingFake({ status: 0, output: '' });

        const check = await checkExtensions({ projectRoot, administrationRoot });
        const result = check.results[0];

        expect(result.typescript.status).toBe('passed');
        expect(result.typescript.output).not.toContain('returned invalid JSON');
        expect(check.exitCode).toBe(0);
    }, 60000);
});
