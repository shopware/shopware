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

    it('fails when no Jest config runs, naming the vendor extensions it left out', async () => {
        writeFile(path.join(fixture.projectRoot, vendorAdmin, 'jest.config.mjs'), 'export default {};');
        fs.rmSync(path.join(fixture.administrationRoot, componentImportsFile));

        const result = await fixture.run({ includeVendors: false });

        expect(fixture.jestRuns).toEqual([]);
        expect(fixture.generatorRuns).toBe(0);
        expect(result.fatalDiagnostics).toEqual([
            expect.stringMatching(
                /^Nothing was tested: .* Drop --no-vendors to test the vendor extensions: custom-admin\.$/,
            ),
        ]);
        expect(result.exitCode).toBe(1);
    });

    it('fails when a Jest run fails', async () => {
        writeFile(path.join(fixture.projectRoot, zeroConfigAdmin, 'jest.config.mjs'), 'export default {};');
        fixture.jestExitCode = 1;

        const result = await fixture.run();

        expect(result.runs).toEqual([
            { extension: 'ZeroConfig', config: `${zeroConfigAdmin}/jest.config.mjs`, exitCode: 1, testCount: 1 },
        ]);
        const report = renderTestReport(result);
        expect(report).toContain(`✗ ZeroConfig · ${zeroConfigAdmin}/jest.config.mjs`);
        expect(report).not.toContain('Suite');
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
            { extension: 'Suite', config: 'custom/plugins/Suite/jest.config.js', exitCode: 1, testCount: 1 },
            { extension: 'ZeroConfig', config: `${zeroConfigAdmin}/jest.config.mjs`, exitCode: 0, testCount: 1 },
        ]);
        const report = renderTestReport(result);
        expect(report).toContain('✗ Suite · custom/plugins/Suite/jest.config.js');
        expect(report).toContain(`✓ ZeroConfig · ${zeroConfigAdmin}/jest.config.mjs`);
        expect(result.exitCode).toBe(1);
    });

    it('fails when no spec ran in any Jest config, although each Jest run passed', async () => {
        writeTwoJestConfigs(fixture.projectRoot);
        fixture.jestTestCount = 0;

        const result = await fixture.run({ jestArgs: ['--ci'] });

        expect(fixture.jestRuns).toHaveLength(2);
        const report = renderTestReport(result);
        expect(report).toContain('– Suite · custom/plugins/Suite/jest.config.js (no matching spec)');
        expect(report).toContain('✗ Nothing was tested: no spec matched in any Jest config.');
        expect(result.exitCode).toBe(1);
    });

    it('passes when another Jest config ran a spec and marks the empty one', async () => {
        writeTwoJestConfigs(fixture.projectRoot);
        fixture.jestTestCountByConfig = { 'custom/plugins/Suite/jest.config.js': 0 };

        const result = await fixture.run({ jestArgs: ['--ci'] });

        const report = renderTestReport(result);
        expect(report).toContain('– Suite · custom/plugins/Suite/jest.config.js (no matching spec)');
        expect(report).toContain(`✓ ZeroConfig · ${zeroConfigAdmin}/jest.config.mjs`);
        expect(result.fatalDiagnostics).toEqual([]);
        expect(result.exitCode).toBe(0);
    });

    it.each([
        { case: 'the caller passes --passWithNoTests', jestTestCount: 0, jestArgs: ['--passWithNoTests'] },
        { case: 'Jest wrote no results', jestTestCount: undefined, jestArgs: ['--ci'] },
    ])('passes without a counted spec when $case', async ({ jestTestCount, jestArgs }) => {
        writeTwoJestConfigs(fixture.projectRoot);
        fixture.jestTestCount = jestTestCount;

        const result = await fixture.run({ jestArgs });

        expect(result.fatalDiagnostics).toEqual([]);
        expect(result.exitCode).toBe(0);
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
