/**
 * @sw-package framework
 */

import { EXIT_FINDINGS, EXIT_OK, EXIT_TOOL_ERROR, exitCodeFor, renderReport, zeroFileErrors } from './report';
import type { AdminRoot, CheckReport, Finding, ToolRun } from './shared';

const root: AdminRoot = {
    bundleName: 'SwagExample',
    technicalName: 'swag-example',
    extensionName: 'SwagExample',
    extensionRoot: '/project/custom/plugins/SwagExample',
    sourcePath: '/project/custom/plugins/SwagExample/src/Resources/app/administration/src',
    adminFolder: '/project/custom/plugins/SwagExample/src/Resources/app/administration',
    slug: 'swag-example',
    platform: false,
};

const finding = (severity: Finding['severity'], rule: string): Finding => ({
    file: 'custom/plugins/SwagExample/src/Resources/app/administration/src/main.ts',
    line: 3,
    column: 7,
    severity,
    rule,
    message: 'something is off',
});

const run = (overrides: Partial<ToolRun> = {}): ToolRun => ({
    tool: 'types',
    filesChecked: 12,
    findings: [],
    externalFindings: 0,
    unresolvedHostModules: 0,
    errors: [],
    ...overrides,
});

const report = (runs: ToolRun[], errors: string[] = []): CheckReport => ({ roots: [{ root, runs }], errors });

const renderOptions = { sourcePaths: { 'swag-example': 'custom/plugins/SwagExample/…/administration/src' } };

describe('scripts/extensionTooling/report', () => {
    describe('exitCodeFor', () => {
        it('exits 0 when files were checked and nothing was found', () => {
            expect(exitCodeFor(report([run()]))).toBe(EXIT_OK);
        });

        it('exits 1 on error findings', () => {
            expect(exitCodeFor(report([run({ findings: [finding('error', 'TS2322')] })]))).toBe(EXIT_FINDINGS);
        });

        it('exits 0 on warnings alone — warnings are reported, not failed on', () => {
            expect(exitCodeFor(report([run({ findings: [finding('warning', 'vue/attributes-order')] })]))).toBe(EXIT_OK);
        });

        it('exits 3 when a run checked zero files, even without findings', () => {
            expect(exitCodeFor(report([run({ filesChecked: 0 })]))).toBe(EXIT_TOOL_ERROR);
        });

        it('exits 3 when a tool failed', () => {
            expect(exitCodeFor(report([run({ errors: ['tsc crashed'] })]))).toBe(EXIT_TOOL_ERROR);
        });

        it('exits 3 on a global tool error', () => {
            expect(exitCodeFor({ roots: [], errors: ['no bundle configuration'] })).toBe(EXIT_TOOL_ERROR);
        });

        it('exits 3 when nothing was checked at all', () => {
            expect(exitCodeFor({ roots: [], errors: [] })).toBe(EXIT_TOOL_ERROR);
        });

        it('prefers the tool error over findings — crash and findings stay distinguishable', () => {
            expect(exitCodeFor(report([run({ filesChecked: 0, findings: [finding('error', 'TS2322')] })]))).toBe(
                EXIT_TOOL_ERROR,
            );
        });
    });

    describe('zeroFileErrors', () => {
        it('names the extension and the tool', () => {
            expect(zeroFileErrors(report([run({ tool: 'lint', filesChecked: 0 })]))).toEqual([
                'Checked 0 files for SwagExample (lint) — this is a tool error, not a clean result.',
            ]);
        });

        it('stays silent when the tool already reported its own failure', () => {
            expect(zeroFileErrors(report([run({ filesChecked: 0, errors: ['binary missing'] })]))).toEqual([]);
        });
    });

    describe('renderReport', () => {
        it('states the number of files checked per root and tool', () => {
            const output = renderReport(
                report([
                    run({ tool: 'types', filesChecked: 12 }),
                    run({ tool: 'lint', filesChecked: 47, findings: [finding('warning', 'vue/attributes-order')] }),
                ]),
                renderOptions,
            );

            expect(output).toContain('types: 12 files type-checked, 0 errors, 0 warnings');
            expect(output).toContain('lint: 47 files linted, 0 errors, 1 warnings');
            expect(output).toContain('Summary: 1 source roots, 47 files checked, 0 errors, 1 warnings');
        });

        it('tells the reader what to do about a broken host type surface', () => {
            const output = renderReport(
                report([
                    run({
                        externalFindings: 70,
                        unresolvedHostModules: 70,
                        errors: [
                            'The Administration type surface did not resolve: 70 unresolved modules in the host sources.',
                        ],
                    }),
                ]),
                renderOptions,
            );

            expect(output).toContain('tool error: The Administration type surface did not resolve');
            expect(output).toContain('Run "npm ci" in the Administration');
        });

        it('counts host diagnostics without listing them', () => {
            const output = renderReport(report([run({ externalFindings: 231 })]), renderOptions);

            expect(output).toContain('231 diagnostics in Administration sources outside this extension, not listed');
        });

        it('renders findings with location and rule', () => {
            const output = renderReport(report([run({ findings: [finding('error', 'TS2322')] })]), renderOptions);

            expect(output).toContain(
                'custom/plugins/SwagExample/src/Resources/app/administration/src/main.ts:3:7  error  TS2322  something is off',
            );
        });

        it('renders a program-level finding without a location', () => {
            const output = renderReport(
                report([
                    run({
                        filesChecked: 1,
                        findings: [
                            {
                                file: null,
                                line: null,
                                column: null,
                                severity: 'error',
                                rule: 'TS18003',
                                message: 'No inputs were found in config file.',
                            },
                        ],
                    }),
                ]),
                renderOptions,
            );

            expect(output).toContain('<program>  error  TS18003  No inputs were found in config file.');
        });

        it('repeats the zero-file tool error at the end of the report', () => {
            expect(renderReport(report([run({ filesChecked: 0 })]), renderOptions)).toContain(
                'tool error: Checked 0 files for SwagExample (types)',
            );
        });

        it('explains an empty run instead of printing a bare success', () => {
            const output = renderReport({ roots: [], errors: [] }, { sourcePaths: {} });

            expect(output).toContain('No Administration extension sources were checked.');
            expect(output).toContain('--include-platform');
        });

        it('prints notices and global tool errors', () => {
            const output = renderReport(
                { roots: [], errors: ['no bundle configuration'] },
                {
                    sourcePaths: {},
                    notices: ['Skipped 3 platform source roots.'],
                },
            );

            expect(output).toContain('Skipped 3 platform source roots.');
            expect(output).toContain('tool error: no bundle configuration');
        });
    });
});
