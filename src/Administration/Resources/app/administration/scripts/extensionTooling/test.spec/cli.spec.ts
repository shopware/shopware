/**
 * @sw-package framework
 */

import path from 'path';
import { promptSelection } from '../select';
import { runTestCli } from '../test';
import { cleanupTempProject, writeFile } from '../test-helpers';
import type { TestFixture } from './helpers';
import { createTestFixture, stubConfigPaths, vendorAdmin, writeStubJestBin, zeroConfigAdmin } from './helpers';

jest.mock('../select');

const mockedPromptSelection = jest.mocked(promptSelection);

describe('scripts/extensionTooling/test CLI', () => {
    let fixture: TestFixture;

    beforeEach(() => {
        fixture = createTestFixture();
    });

    afterEach(() => {
        cleanupTempProject(fixture.projectRoot);
    });

    it('rejects an unknown tooling option without running anything', async () => {
        jest.spyOn(console, 'error').mockImplementation(() => {});

        await expect(
            runTestCli([
                '--onyl=ZeroConfig',
                '--project-root',
                fixture.projectRoot,
                '--administration-root',
                fixture.administrationRoot,
            ]),
        ).resolves.toBe(2);
    });

    describe('runTestCli', () => {
        it('prints help with exit 0', async () => {
            const consoleLog = jest.spyOn(console, 'log').mockImplementation(() => {});

            await expect(runTestCli(['--help'])).resolves.toBe(0);
            expect(consoleLog.mock.calls.join('\n')).toContain('admin:test-extensions');
        });

        it('treats a missing PROJECT_ROOT as a usage error with exit 2', async () => {
            const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {});
            const previousProjectRoot = process.env.PROJECT_ROOT;
            delete process.env.PROJECT_ROOT;

            try {
                await expect(runTestCli(['--all', '--administration-root', fixture.administrationRoot])).resolves.toBe(2);
            } finally {
                if (previousProjectRoot !== undefined) {
                    process.env.PROJECT_ROOT = previousProjectRoot;
                }
            }

            expect(consoleError.mock.calls.join('\n')).toContain('PROJECT_ROOT or --project-root is required.');
        });

        it('forwards the Jest options after the second -- to the Administration Jest', async () => {
            jest.spyOn(console, 'log').mockImplementation(() => {});
            const stubJestRuns = writeStubJestBin(fixture.administrationRoot);
            const configPath = path.join(fixture.projectRoot, zeroConfigAdmin, 'jest.config.mjs');
            writeFile(configPath, 'export default {};');

            await expect(
                runTestCli([
                    '--only=ZeroConfig',
                    '--project-root',
                    fixture.projectRoot,
                    '--administration-root',
                    fixture.administrationRoot,
                    '--',
                    'main.spec',
                    '--ci',
                ]),
            ).resolves.toBe(0);

            expect(stubJestRuns()).toEqual([
                [
                    '--config',
                    configPath,
                    'main.spec',
                    '--ci',
                    expect.stringMatching(/^--testResultsProcessor=.+jest-results-processor\.js$/),
                ],
            ]);
        });

        it.each([
            [['--testResultsProcessor=./own-processor.js']],
            [['--test-results-processor', './own-processor.js']],
        ])('leaves the results processor to a caller who passes %j', async (jestOptions) => {
            jest.spyOn(console, 'log').mockImplementation(() => {});
            const stubJestRuns = writeStubJestBin(fixture.administrationRoot);
            writeFile(path.join(fixture.projectRoot, zeroConfigAdmin, 'jest.config.mjs'), 'export default {};');

            await expect(runCli(['--only=ZeroConfig', '--', ...jestOptions])).resolves.toBe(0);

            expect(stubJestRuns()[0].slice(2)).toEqual(jestOptions);
        });

        it.each([
            [['--json']],
            [['--json', '--outputFile=results.json']],
        ])('still counts the specs when the caller asks for %j', async (jestOptions) => {
            jest.spyOn(console, 'log').mockImplementation(() => {});
            writeStubJestBin(fixture.administrationRoot, 0);
            writeFile(path.join(fixture.projectRoot, zeroConfigAdmin, 'jest.config.mjs'), 'export default {};');

            await expect(runCli(['--only=ZeroConfig', '--', ...jestOptions])).resolves.toBe(1);
        });

        it('fails when the Jest results count no spec', async () => {
            jest.spyOn(console, 'log').mockImplementation(() => {});
            writeStubJestBin(fixture.administrationRoot, 0);
            writeFile(path.join(fixture.projectRoot, zeroConfigAdmin, 'jest.config.mjs'), 'export default {};');

            await expect(runCli(['--only=ZeroConfig'])).resolves.toBe(1);
        });

        it("spawns the extension's own Jest for a config without the harness and labels the run", async () => {
            const consoleLog = jest.spyOn(console, 'log').mockImplementation(() => {});
            const administrationJestRuns = writeStubJestBin(fixture.administrationRoot, 1);
            const ownJestRuns = writeStubJestBin(path.join(fixture.projectRoot, 'custom/plugins/ZeroConfig'), 1);
            writeFile(path.join(fixture.projectRoot, zeroConfigAdmin, 'jest.config.js'), 'module.exports = {};');

            await expect(runCli(['--only=ZeroConfig'])).resolves.toBe(0);

            expect(stubConfigPaths(fixture.projectRoot, ownJestRuns())).toEqual([`${zeroConfigAdmin}/jest.config.js`]);
            expect(administrationJestRuns()).toEqual([]);
            const output = consoleLog.mock.calls.join('\n');
            expect(output).toContain(`▶ ZeroConfig · ${zeroConfigAdmin}/jest.config.js (own Jest)`);
            expect(output).toContain(`✓ ZeroConfig · ${zeroConfigAdmin}/jest.config.js (own Jest)`);
        });

        it('prints a line naming the extension and config before each Jest run', async () => {
            const consoleLog = jest.spyOn(console, 'log').mockImplementation(() => {});
            writeStubJestBin(fixture.administrationRoot, 1);
            writeFile(path.join(fixture.projectRoot, zeroConfigAdmin, 'jest.config.mjs'), 'export default {};');

            await expect(runCli(['--only=ZeroConfig'])).resolves.toBe(0);

            expect(consoleLog.mock.calls.join('\n')).toContain(`▶ ZeroConfig · ${zeroConfigAdmin}/jest.config.mjs`);
        });

        it('tests vendor extensions with --all', async () => {
            jest.spyOn(console, 'log').mockImplementation(() => {});
            const stubJestRuns = writeStubJestBin(fixture.administrationRoot, 1);
            writeFile(path.join(fixture.projectRoot, zeroConfigAdmin, 'jest.config.mjs'), 'export default {};');
            writeFile(path.join(fixture.projectRoot, vendorAdmin, 'jest.config.mjs'), 'export default {};');

            await expect(runCli(['--all'])).resolves.toBe(0);
            expect(stubConfigPaths(fixture.projectRoot, stubJestRuns()).sort()).toEqual([
                `${zeroConfigAdmin}/jest.config.mjs`,
                `${vendorAdmin}/jest.config.mjs`,
            ]);
        });

        it('fails on a failing vendor Jest run only with --strict-vendor', async () => {
            jest.spyOn(console, 'log').mockImplementation(() => {});
            writeFile(path.join(fixture.administrationRoot, 'node_modules/jest/bin/jest.js'), 'process.exitCode = 1;');
            writeFile(path.join(fixture.projectRoot, vendorAdmin, 'jest.config.mjs'), 'export default {};');

            await expect(runCli(['--all'])).resolves.toBe(0);
            await expect(runCli(['--all', '--strict-vendor'])).resolves.toBe(1);
        });
    });

    /** Runs the CLI against the fixture; `args` are the tooling options and, after a `--`, the Jest options. */
    function runCli(args: string[]) {
        const separator = args.indexOf('--');
        const toolingArgs = separator === -1 ? args : args.slice(0, separator);
        const jestArgs = separator === -1 ? [] : args.slice(separator);

        return runTestCli([
            ...toolingArgs,
            '--project-root',
            fixture.projectRoot,
            '--administration-root',
            fixture.administrationRoot,
            ...jestArgs,
        ]);
    }

    describe('interactive picker', () => {
        const ttyDescriptors = {
            stdin: Object.getOwnPropertyDescriptor(process.stdin, 'isTTY'),
            stdout: Object.getOwnPropertyDescriptor(process.stdout, 'isTTY'),
        };

        /** Makes runTestCli take the interactive branch; afterEach restores the real values. */
        function withInteractiveTerminal() {
            Object.defineProperty(process.stdin, 'isTTY', { value: true, configurable: true });
            Object.defineProperty(process.stdout, 'isTTY', { value: true, configurable: true });
        }

        function runInteractive(toolingArgs: string[] = []) {
            return runCli(toolingArgs);
        }

        afterEach(() => {
            for (const [stream, descriptor] of [
                [process.stdin, ttyDescriptors.stdin],
                [process.stdout, ttyDescriptors.stdout],
            ] as const) {
                if (descriptor) {
                    Object.defineProperty(stream, 'isTTY', descriptor);
                } else {
                    delete (stream as { isTTY?: boolean }).isTTY;
                }
            }
        });

        it('lists only extensions with a Jest config and runs every listed one, vendor included, when all are chosen', async () => {
            jest.spyOn(console, 'log').mockImplementation(() => {});
            withInteractiveTerminal();
            const stubJestRuns = writeStubJestBin(fixture.administrationRoot);
            writeFile(path.join(fixture.projectRoot, zeroConfigAdmin, 'jest.config.mjs'), 'export default {};');
            writeFile(path.join(fixture.projectRoot, 'custom/plugins/ZeroConfig/jest.config.js'), 'module.exports = {};');
            writeFile(path.join(fixture.projectRoot, vendorAdmin, 'jest.config.mjs'), 'export default {};');
            mockedPromptSelection.mockResolvedValue('all');

            await expect(runInteractive()).resolves.toBe(0);

            expect(mockedPromptSelection).toHaveBeenCalledTimes(1);
            const [listed, title, describeProject] = mockedPromptSelection.mock.calls[0];
            expect(listed.map((project) => project.name).sort()).toEqual([
                'ZeroConfig',
                'custom-admin',
            ]);
            expect(title).toBe('Select extensions to test (Jest):');
            const zeroConfig = listed.find((project) => project.name === 'ZeroConfig');
            const customAdmin = listed.find((project) => project.name === 'custom-admin');
            expect(describeProject?.(zeroConfig!)).toBe('custom/plugins/ZeroConfig · 2 Jest configs');
            expect(describeProject?.(customAdmin!)).toBe('vendor · 1 Jest config');
            expect(stubConfigPaths(fixture.projectRoot, stubJestRuns()).sort()).toEqual([
                'custom/plugins/ZeroConfig/jest.config.js',
                `${zeroConfigAdmin}/jest.config.mjs`,
                `${vendorAdmin}/jest.config.mjs`,
            ]);
        });

        it('marks a listed extension whose own Jest is not installed yet', async () => {
            jest.spyOn(console, 'log').mockImplementation(() => {});
            withInteractiveTerminal();
            writeFile(path.join(fixture.projectRoot, 'custom/plugins/Suite/jest.config.js'), 'module.exports = {};');
            writeFile(
                path.join(fixture.projectRoot, 'custom/plugins/Suite/package.json'),
                JSON.stringify({ devDependencies: { jest: '^29.7.0' } }),
            );
            mockedPromptSelection.mockResolvedValue('cancel');

            await expect(runInteractive()).resolves.toBe(0);

            const [listed, , describeProject] = mockedPromptSelection.mock.calls[0];
            const suite = listed.find((project) => project.name === 'Suite');
            expect(describeProject?.(suite!)).toBe('custom/plugins/Suite · 1 Jest config · own Jest not installed');
        });

        it('tests every extension and says so without a selection in a non-interactive terminal', async () => {
            const consoleLog = jest.spyOn(console, 'log').mockImplementation(() => {});
            Object.defineProperty(process.stdin, 'isTTY', { value: false, configurable: true });
            const stubJestRuns = writeStubJestBin(fixture.administrationRoot, 1);
            writeFile(path.join(fixture.projectRoot, zeroConfigAdmin, 'jest.config.mjs'), 'export default {};');

            await expect(runInteractive()).resolves.toBe(0);

            expect(mockedPromptSelection).not.toHaveBeenCalled();
            expect(stubConfigPaths(fixture.projectRoot, stubJestRuns())).toEqual([`${zeroConfigAdmin}/jest.config.mjs`]);
            expect(consoleLog.mock.calls.join('\n')).toContain(
                'Not an interactive terminal and no selection given — testing all extensions.',
            );
        });

        it('runs nothing and exits 0 when the picker is cancelled', async () => {
            jest.spyOn(console, 'log').mockImplementation(() => {});
            withInteractiveTerminal();
            const stubJestRuns = writeStubJestBin(fixture.administrationRoot);
            writeFile(path.join(fixture.projectRoot, zeroConfigAdmin, 'jest.config.mjs'), 'export default {};');
            mockedPromptSelection.mockResolvedValue('cancel');

            await expect(runInteractive()).resolves.toBe(0);

            expect(stubConfigPaths(fixture.projectRoot, stubJestRuns())).toEqual([]);
        });

        it('runs only the extensions the picker returns', async () => {
            jest.spyOn(console, 'log').mockImplementation(() => {});
            withInteractiveTerminal();
            const stubJestRuns = writeStubJestBin(fixture.administrationRoot);
            writeFile(path.join(fixture.projectRoot, zeroConfigAdmin, 'jest.config.mjs'), 'export default {};');
            writeFile(path.join(fixture.projectRoot, vendorAdmin, 'jest.config.mjs'), 'export default {};');
            mockedPromptSelection.mockResolvedValue({ names: ['ZeroConfig'] });

            await expect(runInteractive()).resolves.toBe(0);

            expect(stubConfigPaths(fixture.projectRoot, stubJestRuns())).toEqual([`${zeroConfigAdmin}/jest.config.mjs`]);
        });

        it('exits 0 with a message and no picker when no extension has a Jest config', async () => {
            const consoleLog = jest.spyOn(console, 'log').mockImplementation(() => {});
            withInteractiveTerminal();

            await expect(runInteractive()).resolves.toBe(0);

            expect(consoleLog.mock.calls.join('\n')).toContain('No Administration extension has a Jest config');
            expect(mockedPromptSelection).not.toHaveBeenCalled();
        });
    });
});
