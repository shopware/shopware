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
import { runCommand } from '../probe';
import { checkExtensions, listSpecFiles, listTypeCheckableFiles } from '../check';
import {
    cleanupTempProject,
    createSkeletonAdmin,
    createTempProject,
    realAdministrationRoot,
    syntheticEntitySchema,
    writeFile,
    writePluginsConfig,
} from '../test-helpers';

jest.mock('../probe', () => ({
    ...jest.requireActual<Record<string, unknown>>('../probe'),
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

            const base = { durationMs: 60000, timedOut: false };

            if (args.includes('--showConfig')) {
                const files = [
                    ...listTypeCheckableFiles(projectRoot, ['custom/plugins']),
                    ...listSpecFiles(projectRoot, ['custom/plugins']),
                ];

                return { ...base, status: 0, output: JSON.stringify({ files }) };
            }

            if (args[0].includes('eslint')) {
                return { ...base, status: 0, output: '' };
            }

            return { ...base, ...vueTscOutput };
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
});
