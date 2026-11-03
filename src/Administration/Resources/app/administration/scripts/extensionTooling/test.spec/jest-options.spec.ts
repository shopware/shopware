/**
 * @sw-package framework
 */

import path from 'path';
import { CliUsageError } from '../cli';
import { runTestCli } from '../test';
import { cleanupTempProject, writeFile } from '../test-helpers';
import type { TestFixture } from './helpers';
import { createTestFixture, writeTwoJestConfigs, zeroConfigAdmin } from './helpers';

describe('scripts/extensionTooling/test Jest options', () => {
    let fixture: TestFixture;

    beforeEach(() => {
        fixture = createTestFixture();
    });

    afterEach(() => {
        cleanupTempProject(fixture.projectRoot);
    });

    it.each([
        { case: 'one Jest config', only: 'ZeroConfig', jestArgs: ['main.spec'], configs: 1 },
        { case: 'no Jest options', only: undefined, jestArgs: [], configs: 2 },
        {
            case: 'the option with a value already given',
            only: undefined,
            jestArgs: ['main.spec', '--passWithNoTests=true'],
            configs: 2,
        },
        { case: 'the bare option already given', only: undefined, jestArgs: ['main.spec', '--passWithNoTests'], configs: 2 },
        {
            case: 'the kebab-case option given',
            only: undefined,
            jestArgs: ['main.spec', '--pass-with-no-tests'],
            configs: 2,
        },
        { case: 'the option turned off', only: undefined, jestArgs: ['main.spec', '--no-passWithNoTests'], configs: 2 },
        {
            case: 'a separate false value',
            only: undefined,
            jestArgs: ['main.spec', '--passWithNoTests', 'false'],
            configs: 2,
        },
    ])('forwards the Jest options unchanged for $case', async ({ only, jestArgs, configs }) => {
        writeTwoJestConfigs(fixture.projectRoot);

        await fixture.run({ only, jestArgs });

        expect(fixture.jestRuns.map((jestRun) => jestRun.jestArgs)).toEqual(Array(configs).fill(jestArgs));
    });

    it('runs watch mode for a selection with exactly one Jest config', async () => {
        writeTwoJestConfigs(fixture.projectRoot);

        const result = await fixture.run({ only: 'ZeroConfig', jestArgs: ['--watch'] });

        expect(fixture.jestRuns).toEqual([{ configPath: `${zeroConfigAdmin}/jest.config.mjs`, jestArgs: ['--watch'] }]);
        expect(result.exitCode).toBe(0);
    });

    it.each([
        [['--watch']],
        [['--watchAll']],
        [['--watch=true']],
        [['--watchAll=true']],
        [['--no-watch', '--watch']],
        [['--watch', '--no-watchAll']],
        [['--watch-all']],
        [['--watch-all=true']],
        [['--watch', 'true']],
    ])('refuses %j over more than one Jest config without running Jest', async (watchOptions) => {
        writeTwoJestConfigs(fixture.projectRoot);

        await expect(fixture.run({ jestArgs: watchOptions })).rejects.toThrow(CliUsageError);
        expect(fixture.jestRuns).toEqual([]);
    });

    it.each([
        [['--watch=false']],
        [['--watchAll=false']],
        [['--no-watch']],
        [['--no-watchAll']],
        [['--watch', '--watch=false']],
        [['--watchman']],
        [['--watch', 'false']],
        [['--watch-all', 'false']],
        [['--no-watch-all']],
    ])('runs every Jest config for %j, which starts no watch mode', async (watchOptions) => {
        writeTwoJestConfigs(fixture.projectRoot);

        const result = await fixture.run({ jestArgs: watchOptions });

        expect(fixture.jestRuns).toHaveLength(2);
        expect(result.exitCode).toBe(0);
    });

    it('names the Jest configs when watch mode targets one extension that has several', async () => {
        writeFile(path.join(fixture.projectRoot, 'custom/plugins/ZeroConfig/jest.config.js'), 'module.exports = {};');
        writeFile(path.join(fixture.projectRoot, zeroConfigAdmin, 'jest.config.mjs'), 'export default {};');

        const watchRun = fixture.run({ only: 'ZeroConfig', jestArgs: ['--watch'] });

        await expect(watchRun).rejects.toThrow(CliUsageError);
        await expect(watchRun).rejects.toThrow(
            `ZeroConfig has 2 Jest configs: custom/plugins/ZeroConfig/jest.config.js, ${zeroConfigAdmin}/jest.config.mjs.`,
        );
        await expect(watchRun).rejects.not.toThrow('Name one extension');
        expect(fixture.jestRuns).toEqual([]);
    });

    it.each([
        'Renamed',
        'ZeroConfig,Suite',
    ])('reports a fatal selection error instead of a watch usage error for --only=%s', async (only) => {
        writeFile(path.join(fixture.projectRoot, 'custom/plugins/ZeroConfig/jest.config.js'), 'module.exports = {};');
        writeFile(path.join(fixture.projectRoot, zeroConfigAdmin, 'jest.config.mjs'), 'export default {};');

        const result = await fixture.run({ only, jestArgs: ['--watch'] });

        expect(result.fatalDiagnostics).toHaveLength(1);
        expect(result.exitCode).toBe(1);
        expect(fixture.jestRuns).toEqual([]);
    });

    it('exits with a usage error when watch mode has no single Jest config to run', async () => {
        const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {});

        await expect(
            runTestCli([
                '--all',
                '--project-root',
                fixture.projectRoot,
                '--administration-root',
                fixture.administrationRoot,
                '--',
                '--watch',
            ]),
        ).resolves.toBe(2);
        expect(consoleError).toHaveBeenCalledWith(expect.stringContaining('Name one extension via --only'));
    });
});
