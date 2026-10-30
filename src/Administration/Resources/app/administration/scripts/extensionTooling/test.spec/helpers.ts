/**
 * @sw-package framework
 *
 * The `admin:test-extensions` contract: which Jest configs run for a
 * selection, what reaches Jest, and when the run fails. Jest itself and the
 * component import generator are replaced by recorders; the e2e suite runs the real Jest.
 */

import fs from 'fs';
import path from 'path';
import { testExtensions } from '../test';
import type { TestExtensionsOptions, TestExtensionsResult } from '../test';
import { writeFile } from '../test-helpers';
import { createSetupProject, writeDefaultFixtures } from '../setup.spec/fixtures';

export const zeroConfigAdmin = 'custom/plugins/ZeroConfig/src/Resources/app/administration';
export const componentImportsFile = 'test/_helper_/componentWrapper/component-imports.js';
export const vendorAdmin = 'vendor/acme/custom-admin/src/Resources/app/administration';

/**
 * A temp project with the default extensions, plus recorders for Jest and the
 * component import generator. Tests set the exit codes before `run()` and read
 * the recorded runs after it.
 */
export interface TestFixture {
    projectRoot: string;
    administrationRoot: string;
    /** `ownJestBin` is set, relative to the project root, for a run on the extension's own Jest. */
    jestRuns: Array<{ configPath: string; jestArgs: string[]; ownJestBin?: string }>;
    jestExitCode: number;
    /** Overrides jestExitCode for single configs, keyed by the config path relative to the project root. */
    jestExitCodeByConfig: Record<string, number>;
    /** The tests each Jest run reports; undefined stands for a run that wrote no results. */
    jestTestCount: number | undefined;
    /** Overrides jestTestCount for single configs, keyed like jestExitCodeByConfig. */
    jestTestCountByConfig: Record<string, number>;
    generatorRuns: number;
    generatorExitCode: number;
    run(options?: Partial<TestExtensionsOptions>): Promise<TestExtensionsResult>;
}

/**
 * Creates a fresh fixture; call it in beforeEach and pass `projectRoot` to
 * cleanupTempProject in afterEach.
 *
 * @example
 * fixture = createTestFixture();
 * fixture.jestExitCode = 1;
 * const result = await fixture.run({ only: 'ZeroConfig' });
 */
export function createTestFixture(): TestFixture {
    const { projectRoot, administrationRoot } = createSetupProject('sw-tooling-test-');
    writeDefaultFixtures(projectRoot);
    writeFile(path.join(administrationRoot, componentImportsFile), 'export {};');

    const fixture: TestFixture = {
        projectRoot,
        administrationRoot,
        jestRuns: [],
        jestExitCode: 0,
        jestExitCodeByConfig: {},
        jestTestCount: 1,
        jestTestCountByConfig: {},
        generatorRuns: 0,
        generatorExitCode: 0,
        run: (options = {}) =>
            testExtensions({
                projectRoot,
                administrationRoot,
                runJest: (configPath, jestArgs, ownJestBin) => {
                    const relativeConfigPath = path.relative(projectRoot, configPath);
                    fixture.jestRuns.push({
                        configPath: relativeConfigPath,
                        jestArgs,
                        ...(ownJestBin === undefined ? {} : { ownJestBin: path.relative(projectRoot, ownJestBin) }),
                    });

                    return Promise.resolve({
                        exitCode: fixture.jestExitCodeByConfig[relativeConfigPath] ?? fixture.jestExitCode,
                        testCount: fixture.jestTestCountByConfig[relativeConfigPath] ?? fixture.jestTestCount,
                    });
                },
                generateComponentImports: () => {
                    fixture.generatorRuns += 1;

                    return fixture.generatorExitCode;
                },
                ...options,
            }),
    };

    return fixture;
}

/**
 * Replaces the Jest bin under `packageRoot` (the Administration's, or an
 * extension's own) with a script that logs the arguments of each run, so the
 * real runner spawns without running Jest. With `testCount`, the script hands
 * that many passed tests to the `--testResultsProcessor`, as Jest does.
 */
export function writeStubJestBin(packageRoot: string, testCount?: number): () => string[][] {
    const logPath = path.join(packageRoot, 'node_modules/jest/bin/runs.log');
    writeFile(
        path.join(packageRoot, 'node_modules/jest/bin/jest.js'),
        [
            "const fs = require('fs');",
            `fs.appendFileSync(${JSON.stringify(logPath)}, JSON.stringify(process.argv.slice(2)) + '\\n');`,
            ...(testCount === undefined
                ? []
                : [
                      "const processor = process.argv.find((arg) => arg.startsWith('--testResultsProcessor='));",
                      `if (processor) require(processor.slice('--testResultsProcessor='.length))({ numPassedTests: ${testCount}, numFailedTests: 0 });`,
                  ]),
        ].join('\n'),
    );

    return () =>
        fs.existsSync(logPath)
            ? fs
                  .readFileSync(logPath, 'utf8')
                  .split('\n')
                  .filter(Boolean)
                  .map((line) => JSON.parse(line) as string[])
            : [];
}

/** The config path of each stub Jest run, relative to the project root. */
export function stubConfigPaths(projectRoot: string, stubJestRuns: string[][]): string[] {
    return stubJestRuns.map((argv) => path.relative(projectRoot, argv[argv.indexOf('--config') + 1]));
}

/** Gives ZeroConfig's Administration folder and the Suite plugin root a Jest config each. */
export function writeTwoJestConfigs(projectRoot: string): void {
    writeFile(path.join(projectRoot, zeroConfigAdmin, 'jest.config.mjs'), 'export default {};');
    writeFile(path.join(projectRoot, 'custom/plugins/Suite/jest.config.js'), 'module.exports = {};');
}
