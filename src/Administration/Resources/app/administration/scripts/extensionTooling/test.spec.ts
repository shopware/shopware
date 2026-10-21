/**
 * @sw-package framework
 *
 * The `admin:test-extensions` contract: which Jest configs run for a
 * selection, what reaches Jest, and when the run fails. Jest itself and the
 * component import generator are replaced by recorders; the e2e suite runs the
 * real ones.
 */

import fs from 'fs';
import path from 'path';
import { runTestCli, testExtensions } from './test';
import type { TestExtensionsOptions } from './test';
import { cleanupTempProject, writeFile } from './test-helpers';
import { createSetupProject, writeDefaultFixtures } from './setup.spec/fixtures';

const zeroConfigAdmin = 'custom/plugins/ZeroConfig/src/Resources/app/administration';

describe('scripts/extensionTooling/test', () => {
    let projectRoot: string;
    let administrationRoot: string;
    let jestRuns: Array<{ configPath: string; jestArgs: string[] }>;
    let jestExitCode: number;
    let generatorRuns: number;

    function run(options: Partial<TestExtensionsOptions> = {}) {
        return testExtensions({
            projectRoot,
            administrationRoot,
            runJest: (configPath, jestArgs) => {
                jestRuns.push({ configPath: path.relative(projectRoot, configPath), jestArgs });

                return Promise.resolve(jestExitCode);
            },
            generateComponentImports: () => {
                generatorRuns += 1;

                return 0;
            },
            ...options,
        });
    }

    beforeEach(() => {
        ({ projectRoot, administrationRoot } = createSetupProject('sw-tooling-test-'));
        writeDefaultFixtures(projectRoot);
        writeFile(path.join(administrationRoot, 'test/_helper_/componentWrapper/component-imports.js'), 'export {};');
        jestRuns = [];
        jestExitCode = 0;
        generatorRuns = 0;
    });

    afterEach(() => {
        cleanupTempProject(projectRoot);
    });

    it('runs the Jest config of every extension that has one and forwards the Jest options', async () => {
        writeFile(path.join(projectRoot, zeroConfigAdmin, 'jest.config.mjs'), 'export default {};');
        writeFile(path.join(projectRoot, 'custom/plugins/Suite/jest.config.js'), 'module.exports = {};');

        const result = await run({ jestArgs: ['--watch', 'main.spec'] });

        expect(jestRuns).toEqual([
            { configPath: 'custom/plugins/Suite/jest.config.js', jestArgs: ['--watch', 'main.spec'] },
            { configPath: `${zeroConfigAdmin}/jest.config.mjs`, jestArgs: ['--watch', 'main.spec'] },
        ]);
        expect(result.withoutConfig).toEqual(['custom-admin']);
        expect(result.exitCode).toBe(0);
    });

    it('fails when a Jest run fails', async () => {
        writeFile(path.join(projectRoot, zeroConfigAdmin, 'jest.config.mjs'), 'export default {};');
        jestExitCode = 1;

        const result = await run();

        expect(result.runs).toEqual([
            { extension: 'ZeroConfig', config: `${zeroConfigAdmin}/jest.config.mjs`, exitCode: 1 },
        ]);
        expect(result.exitCode).toBe(1);
    });

    it('fails without running Jest when a named extension has no Jest config', async () => {
        writeFile(path.join(projectRoot, zeroConfigAdmin, 'jest.config.mjs'), 'export default {};');

        const result = await run({ only: 'ZeroConfig,Suite' });

        expect(jestRuns).toEqual([]);
        expect(result.fatalDiagnostics).toEqual([expect.stringContaining('found for: Suite')]);
        expect(result.exitCode).toBe(1);
    });

    it('fails without running Jest for an unknown extension name', async () => {
        const result = await run({ only: 'Renamed' });

        expect(jestRuns).toEqual([]);
        expect(result.fatalDiagnostics).toEqual([expect.stringContaining('--only names unknown extension(s): Renamed')]);
        expect(result.exitCode).toBe(1);
    });

    it('generates the component import map only when it is missing', async () => {
        writeFile(path.join(projectRoot, zeroConfigAdmin, 'jest.config.mjs'), 'export default {};');

        await run();
        expect(generatorRuns).toBe(0);

        fs.rmSync(path.join(administrationRoot, 'test/_helper_/componentWrapper/component-imports.js'));
        await run();
        expect(generatorRuns).toBe(1);
    });

    it('rejects an unknown tooling option without running anything', async () => {
        jest.spyOn(console, 'error').mockImplementation(() => {});

        await expect(
            runTestCli([
                '--onyl=ZeroConfig',
                '--project-root',
                projectRoot,
                '--administration-root',
                administrationRoot,
            ]),
        ).resolves.toBe(2);
    });
});
