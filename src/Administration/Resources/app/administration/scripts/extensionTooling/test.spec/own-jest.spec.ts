/**
 * @sw-package framework
 *
 * Which Jest runs a config: the Administration's for a config on its harness,
 * the extension's own for any other config, and none yet when the extension
 * declares a Jest it has not installed.
 */

import path from 'path';
import { renderTestReport } from '../test';
import { cleanupTempProject, writeFile } from '../test-helpers';
import type { TestFixture } from './helpers';
import { createTestFixture, zeroConfigAdmin } from './helpers';

const harnessConfig =
    "import { shopwareAdminJestConfig } from './.shopware/jest.mjs';\n\nexport default shopwareAdminJestConfig({});";
const suiteConfig = 'custom/plugins/Suite/jest.config.js';

describe('scripts/extensionTooling/test own Jest', () => {
    let fixture: TestFixture;

    beforeEach(() => {
        fixture = createTestFixture();
    });

    afterEach(() => {
        cleanupTempProject(fixture.projectRoot);
    });

    function writeOwnJest(directory: string): void {
        writeFile(path.join(fixture.projectRoot, directory, 'node_modules/jest/bin/jest.js'), '');
    }

    function declareJest(directory: string, withLockfile: boolean): void {
        writeFile(
            path.join(fixture.projectRoot, directory, 'package.json'),
            JSON.stringify({ devDependencies: { jest: '^29.7.0' } }),
        );

        if (withLockfile) {
            writeFile(path.join(fixture.projectRoot, directory, 'package-lock.json'), '{}');
        }
    }

    it('runs a config without the harness on the Jest the extension installs above it', async () => {
        writeFile(path.join(fixture.projectRoot, zeroConfigAdmin, 'jest.config.js'), 'module.exports = {};');
        writeOwnJest('custom/plugins/ZeroConfig');

        const result = await fixture.run({ only: 'ZeroConfig' });

        expect(fixture.jestRuns).toEqual([
            {
                configPath: `${zeroConfigAdmin}/jest.config.js`,
                jestArgs: [],
                ownJestBin: 'custom/plugins/ZeroConfig/node_modules/jest/bin/jest.js',
            },
        ]);
        expect(renderTestReport(result)).toContain(`✓ ZeroConfig · ${zeroConfigAdmin}/jest.config.js (own Jest)`);
        expect(result.exitCode).toBe(0);
    });

    it.each([
        ['the bridge', "export { default } from './.shopware/jest.mjs';"],
        [
            'the factory',
            "import createConfig from '../../vendor/shopware/administration/extension-tooling/jest.mjs';\n" +
                'export default createConfig({ rootDir: import.meta.dirname });',
        ],
        [
            "a shared file's shopwareAdminJest",
            "import { shopwareAdminJest } from '../../../tests/jest/harness.mjs';\n" +
                'export default shopwareAdminJest({ rootDir: import.meta.dirname });',
        ],
    ])(
        "runs a config built on %s on the Administration's Jest, even when the extension installs its own",
        async (_source, config) => {
            writeFile(path.join(fixture.projectRoot, zeroConfigAdmin, 'jest.config.mjs'), config);
            writeOwnJest(zeroConfigAdmin);

            const result = await fixture.run({ only: 'ZeroConfig' });

            expect(fixture.jestRuns).toEqual([{ configPath: `${zeroConfigAdmin}/jest.config.mjs`, jestArgs: [] }]);
            expect(result.runs[0].ownJest).toBeUndefined();
        },
    );

    it("runs a config without the harness and without a Jest of its own on the Administration's Jest", async () => {
        writeFile(path.join(fixture.projectRoot, suiteConfig), 'module.exports = {};');

        const result = await fixture.run({ only: 'Suite' });

        expect(fixture.jestRuns).toEqual([{ configPath: suiteConfig, jestArgs: [] }]);
        expect(result.notRunnable).toEqual([]);
    });

    it.each([
        ['with', true, 'npm ci'],
        ['without', false, 'npm install'],
    ])(
        'leaves out a config whose declared Jest is not installed, %s a lockfile, and says how to install it',
        async (_lockfile, withLockfile, installCommand) => {
            writeFile(path.join(fixture.projectRoot, suiteConfig), 'module.exports = {};');
            declareJest('custom/plugins/Suite', withLockfile);
            writeFile(path.join(fixture.projectRoot, zeroConfigAdmin, 'jest.config.mjs'), harnessConfig);

            const result = await fixture.run();

            expect(fixture.jestRuns.map((jestRun) => jestRun.configPath)).toEqual([`${zeroConfigAdmin}/jest.config.mjs`]);
            expect(result.notRunnable).toEqual([
                {
                    extension: 'Suite',
                    config: suiteConfig,
                    installDirectory: 'custom/plugins/Suite',
                    installCommand,
                    failsRun: false,
                },
            ]);
            const report = renderTestReport(result);
            expect(report).toContain(`⊘ Suite · ${suiteConfig} (not run)`);
            expect(report).toContain(`Fix: run \`${installCommand}\` in custom/plugins/Suite,`);
            expect(result.exitCode).toBe(0);
        },
    );

    it('fails when an extension named via --only cannot run its config yet', async () => {
        writeFile(path.join(fixture.projectRoot, suiteConfig), 'module.exports = {};');
        declareJest('custom/plugins/Suite', true);

        const result = await fixture.run({ only: 'Suite' });

        expect(fixture.jestRuns).toEqual([]);
        expect(renderTestReport(result)).toContain(`✗ Suite · ${suiteConfig} (not run)`);
        expect(result.fatalDiagnostics).toEqual([
            'Nothing was tested: no Jest config of this run can run yet, see the fixes above.',
        ]);
        expect(result.exitCode).toBe(1);
    });

    it('fails when no Jest config of the run can run yet, although none was named', async () => {
        writeFile(path.join(fixture.projectRoot, suiteConfig), 'module.exports = {};');
        declareJest('custom/plugins/Suite', true);

        const result = await fixture.run();

        expect(fixture.jestRuns).toEqual([]);
        expect(result.notRunnable.map((config) => config.failsRun)).toEqual([false]);
        expect(result.exitCode).toBe(1);
    });
});
