/**
 * @sw-package framework
 */

import fs from 'fs';
import path from 'path';
import { renderTestReport } from '../test';
import { cleanupTempProject, writeFile } from '../test-helpers';
import type { TestFixture } from './helpers';
import { componentImportsFile, createTestFixture, vendorAdmin, writeTwoJestConfigs, zeroConfigAdmin } from './helpers';

describe('scripts/extensionTooling/test failures', () => {
    let fixture: TestFixture;

    beforeEach(() => {
        fixture = createTestFixture();
    });

    afterEach(() => {
        cleanupTempProject(fixture.projectRoot);
    });

    it('fails when no Jest config runs, naming the vendor extensions it skipped', async () => {
        writeFile(path.join(fixture.projectRoot, vendorAdmin, 'jest.config.mjs'), 'export default {};');
        fs.rmSync(path.join(fixture.administrationRoot, componentImportsFile));

        const result = await fixture.run();

        expect(fixture.jestRuns).toEqual([]);
        expect(fixture.generatorRuns).toBe(0);
        expect(result.fatalDiagnostics).toEqual([
            expect.stringMatching(/^Nothing was tested: .* Name a vendor extension via --only to test it: custom-admin\.$/),
        ]);
        expect(result.exitCode).toBe(1);
    });

    it('fails when a Jest run fails', async () => {
        writeFile(path.join(fixture.projectRoot, zeroConfigAdmin, 'jest.config.mjs'), 'export default {};');
        fixture.jestExitCode = 1;

        const result = await fixture.run();

        expect(result.runs).toEqual([
            { extension: 'ZeroConfig', config: `${zeroConfigAdmin}/jest.config.mjs`, exitCode: 1 },
        ]);
        const report = renderTestReport(result);
        expect(report).toContain(`✗ ZeroConfig · ${zeroConfigAdmin}/jest.config.mjs`);
        expect(report).toContain('No Jest config, not tested: Suite');
        expect(result.exitCode).toBe(1);
    });

    it('fails the run when one of several Jest configs fails and still runs the others', async () => {
        writeTwoJestConfigs(fixture.projectRoot);
        fixture.jestExitCodeByConfig = { 'custom/plugins/Suite/jest.config.js': 1 };

        const result = await fixture.run();

        expect(fixture.jestRuns.map((jestRun) => jestRun.configPath)).toEqual([
            'custom/plugins/Suite/jest.config.js',
            `${zeroConfigAdmin}/jest.config.mjs`,
        ]);
        expect(result.runs).toEqual([
            { extension: 'Suite', config: 'custom/plugins/Suite/jest.config.js', exitCode: 1 },
            { extension: 'ZeroConfig', config: `${zeroConfigAdmin}/jest.config.mjs`, exitCode: 0 },
        ]);
        const report = renderTestReport(result);
        expect(report).toContain('✗ Suite · custom/plugins/Suite/jest.config.js');
        expect(report).toContain(`✓ ZeroConfig · ${zeroConfigAdmin}/jest.config.mjs`);
        expect(result.exitCode).toBe(1);
    });

    it('fails without running Jest when a named extension has no Jest config', async () => {
        writeFile(path.join(fixture.projectRoot, zeroConfigAdmin, 'jest.config.mjs'), 'export default {};');

        const result = await fixture.run({ only: 'ZeroConfig,Suite' });

        expect(fixture.jestRuns).toEqual([]);
        expect(result.fatalDiagnostics).toEqual([expect.stringContaining('found for: Suite')]);
        expect(renderTestReport(result)).toContain('✗ No Jest config (');
        expect(renderTestReport(result)).not.toContain('not tested:');
        expect(result.exitCode).toBe(1);
    });

    it('fails without running Jest for an unknown extension name', async () => {
        const result = await fixture.run({ only: 'Renamed' });

        expect(fixture.jestRuns).toEqual([]);
        expect(result.fatalDiagnostics).toEqual([expect.stringContaining('--only names unknown extension(s): Renamed')]);
        expect(result.exitCode).toBe(1);
    });

    it('generates the component import map only when it is missing', async () => {
        writeFile(path.join(fixture.projectRoot, zeroConfigAdmin, 'jest.config.mjs'), 'export default {};');

        await fixture.run();
        expect(fixture.generatorRuns).toBe(0);

        fs.rmSync(path.join(fixture.administrationRoot, componentImportsFile));
        await fixture.run();
        expect(fixture.generatorRuns).toBe(1);
    });

    it('fails without running Jest when generating the component import map fails', async () => {
        writeFile(path.join(fixture.projectRoot, zeroConfigAdmin, 'jest.config.mjs'), 'export default {};');
        fs.rmSync(path.join(fixture.administrationRoot, componentImportsFile));
        fixture.generatorExitCode = 1;

        const result = await fixture.run();

        expect(fixture.generatorRuns).toBe(1);
        expect(fixture.jestRuns).toEqual([]);
        expect(result.fatalDiagnostics).toEqual([
            'Generating test/_helper_/componentWrapper/component-imports.js failed, see the output above.',
        ]);
        expect(result.exitCode).toBe(1);
    });
});
