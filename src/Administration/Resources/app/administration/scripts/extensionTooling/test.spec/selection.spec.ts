/**
 * @sw-package framework
 */

import path from 'path';
import { findJestConfigs, renderTestReport } from '../test';
import { discoverProjects } from '../setup';
import { pluginsConfigPath } from '../shared';
import { cleanupTempProject, writeFile } from '../test-helpers';
import type { TestFixture } from './helpers';
import { createTestFixture, vendorAdmin, writeTwoJestConfigs, zeroConfigAdmin } from './helpers';

describe('scripts/extensionTooling/test selection', () => {
    let fixture: TestFixture;

    beforeEach(() => {
        fixture = createTestFixture();
    });

    afterEach(() => {
        cleanupTempProject(fixture.projectRoot);
    });

    it('runs the Jest config of every extension that has one and forwards the Jest options', async () => {
        writeTwoJestConfigs(fixture.projectRoot);

        const result = await fixture.run({ jestArgs: ['--ci', 'main.spec'] });

        expect(fixture.jestRuns).toEqual([
            {
                configPath: 'custom/plugins/Suite/jest.config.js',
                jestArgs: ['--ci', 'main.spec', '--passWithNoTests'],
            },
            {
                configPath: `${zeroConfigAdmin}/jest.config.mjs`,
                jestArgs: ['--ci', 'main.spec', '--passWithNoTests'],
            },
        ]);
        expect(result.withoutConfig).toEqual([]);
        expect(renderTestReport(result)).toContain('✓ Suite · custom/plugins/Suite/jest.config.js');
        expect(result.exitCode).toBe(0);
    });

    it('skips a vendor extension unless --only names it', async () => {
        writeFile(path.join(fixture.projectRoot, zeroConfigAdmin, 'jest.config.mjs'), 'export default {};');
        writeFile(path.join(fixture.projectRoot, vendorAdmin, 'jest.config.mjs'), 'export default {};');

        const result = await fixture.run();

        expect(fixture.jestRuns.map((jestRun) => jestRun.configPath)).toEqual([`${zeroConfigAdmin}/jest.config.mjs`]);
        expect(result.vendorNotTested).toEqual(['custom-admin']);
        expect(renderTestReport(result)).toContain('Vendor extensions not tested (name them via --only): custom-admin');
        expect(result.exitCode).toBe(0);
    });

    it('leaves vendor extensions without a Jest config out of the vendor report', async () => {
        writeFile(path.join(fixture.projectRoot, zeroConfigAdmin, 'jest.config.mjs'), 'export default {};');

        const result = await fixture.run();

        expect(result.vendorNotTested).toEqual([]);
        expect(renderTestReport(result)).not.toContain('Vendor extensions not tested');
    });

    it('runs a vendor extension named by its bundle name', async () => {
        writeFile(path.join(fixture.projectRoot, vendorAdmin, 'jest.config.mjs'), 'export default {};');

        const result = await fixture.run({ only: 'CustomAdmin' });

        expect(fixture.jestRuns.map((jestRun) => jestRun.configPath)).toEqual([`${vendorAdmin}/jest.config.mjs`]);
        expect(result.vendorNotTested).toEqual([]);
        expect(result.exitCode).toBe(0);
    });

    // The vendor fixture ships configs that do not compose the Shopware presets, so setup warns about them.
    it('reports the setup warnings of a selected extension and still runs its Jest config', async () => {
        writeFile(path.join(fixture.projectRoot, vendorAdmin, 'jest.config.mjs'), 'export default {};');

        const result = await fixture.run({ only: 'custom-admin' });

        expect(fixture.jestRuns.map((jestRun) => jestRun.configPath)).toEqual([`${vendorAdmin}/jest.config.mjs`]);
        expect(result.setupWarnings).toEqual([
            expect.stringContaining(`${vendorAdmin}/tsconfig.json already exists`),
            expect.stringContaining(`${vendorAdmin}/eslint.config.mjs already exists`),
        ]);
        expect(renderTestReport(result)).toContain(`⚠ ${result.setupWarnings[0]}`);
        expect(result.exitCode).toBe(0);
    });

    it('leaves out the setup warnings of extensions outside the selection', async () => {
        writeFile(path.join(fixture.projectRoot, zeroConfigAdmin, 'jest.config.mjs'), 'export default {};');

        const result = await fixture.run({ only: 'ZeroConfig' });

        expect(result.setupWarnings).toEqual([]);
        expect(renderTestReport(result)).not.toContain('⚠');
    });

    it.each([
        ['tsconfig.json', '{}'],
        ['eslint.config.mjs', 'export default [];'],
    ])('runs the Jest config beside an extension-owned %s', async (ownedConfigFile, ownedConfigContent) => {
        const ownedConfigDir = 'custom/plugins/Suite/src/BundleA/Resources/app';
        writeFile(path.join(fixture.projectRoot, ownedConfigDir, ownedConfigFile), ownedConfigContent);
        writeFile(path.join(fixture.projectRoot, ownedConfigDir, 'jest.config.mjs'), 'export default {};');

        await fixture.run({ only: 'Suite' });

        expect(fixture.jestRuns.map((jestRun) => jestRun.configPath)).toContain(`${ownedConfigDir}/jest.config.mjs`);
    });

    // Setup scaffolds a tsconfig.json beside an owned ESLint config, so only a lookup before setup isolates the ESLint half.
    it('finds the Jest config beside an extension-owned ESLint config without a tsconfig', () => {
        const ownedConfigDir = 'custom/plugins/Suite/src/BundleA/Resources/app';
        writeFile(path.join(fixture.projectRoot, ownedConfigDir, 'eslint.config.mjs'), 'export default [];');
        writeFile(path.join(fixture.projectRoot, ownedConfigDir, 'jest.config.mjs'), 'export default {};');
        const suite = discoverProjects(
            fixture.projectRoot,
            fixture.administrationRoot,
            pluginsConfigPath(fixture.projectRoot),
        ).find((project) => project.name === 'Suite');

        expect(suite).toBeDefined();
        expect(
            findJestConfigs(fixture.projectRoot, suite!).map((config) => path.relative(fixture.projectRoot, config)),
        ).toContain(`${ownedConfigDir}/jest.config.mjs`);
    });

    it('runs only one Jest config per directory, preferring jest.config.js', async () => {
        writeFile(path.join(fixture.projectRoot, zeroConfigAdmin, 'jest.config.js'), 'module.exports = {};');
        writeFile(path.join(fixture.projectRoot, zeroConfigAdmin, 'jest.config.ts'), 'export default {};');

        await fixture.run();

        expect(fixture.jestRuns.map((jestRun) => jestRun.configPath)).toEqual([`${zeroConfigAdmin}/jest.config.js`]);
    });
});
