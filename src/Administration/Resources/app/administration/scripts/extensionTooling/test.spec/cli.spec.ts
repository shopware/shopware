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
                ],
            ]);
        });
    });

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

        function runInteractive() {
            return runTestCli([
                '--project-root',
                fixture.projectRoot,
                '--administration-root',
                fixture.administrationRoot,
            ]);
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
