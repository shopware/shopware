/**
 * @sw-package framework
 */

import { extension, project, report, run } from './helpers';

describe('renderCheckReport findings baseline', () => {
    const baselinedRefs = [
        { file: 'custom/plugins/MyPlugin/src/a.ts', code: 'TS2322' },
        { file: 'custom/plugins/MyPlugin/src/b.ts', code: 'TS2531' },
    ];

    it('reads a fully baselined tool as a green pass with the baselined count', () => {
        const output = report([
            extension(project('MyPlugin'), {
                typescript: run('passed', { findings: 5, newFindings: 0, baselinedFindings: 5 }),
            }),
        ]);

        expect(output).toContain('✔ passed');
        expect(output).toContain('(5 baselined');
        // A fully baselined pass suppresses the raw diagnostics like any pass.
        expect(output).not.toContain('exit 1');
        expect(output).toContain('5 baselined');
    });

    // Baselining used to look irreversible: the count was the only trace, and it
    // named no way to see the suppressed findings again.
    it('names the flag that shows the suppressed findings, and lists them under it', () => {
        const baselined = extension(project('MyPlugin'), {
            typescript: run('passed', {
                findings: 2,
                newFindings: 0,
                baselinedFindings: 2,
                baselinedFindingRefs: baselinedRefs,
                output: 'custom/plugins/MyPlugin/src/a.ts(1,1): error TS2322: nope',
            }),
        });
        const quiet = report([baselined]);

        expect(quiet).toContain('(2 baselined — show with -- --verbose)');
        expect(quiet).not.toContain('baselined — suppressed');

        const verbose = report([baselined], {}, true);

        expect(verbose).toContain('baselined — suppressed (2):');
        expect(verbose).toContain('custom/plugins/MyPlugin/src/a.ts · TS2322');
        expect(verbose).toContain('custom/plugins/MyPlugin/src/b.ts · TS2531');
        // The hint would be stale advice in the run that already followed it.
        expect(verbose).not.toContain('show with -- --verbose');
    });

    // The raw dump lists new and baselined findings in the tool's own order with
    // nothing to tell them apart, so both groups get their own labelled list.
    it('groups the new findings against the baselined ones on a failing run', () => {
        const output = report(
            [
                extension(project('MyPlugin'), {
                    eslint: run('failed', {
                        findings: 3,
                        newFindings: 1,
                        baselinedFindings: 2,
                        newFindingRefs: [{ file: 'custom/plugins/MyPlugin/src/c.ts', code: 'no-console' }],
                        baselinedFindingRefs: baselinedRefs,
                        output: 'custom/plugins/MyPlugin/src/c.ts\n  1:1  error  Unexpected console  no-console',
                    }),
                }),
            ],
            { exitCode: 1 },
        );

        expect(output).toContain('new — must fix to pass (1):');
        expect(output).toContain('custom/plugins/MyPlugin/src/c.ts · no-console');
        expect(output).toContain('baselined — suppressed (2):');
        expect(output.indexOf('new — must fix to pass')).toBeLessThan(output.indexOf('baselined — suppressed'));
    });

    it('splits new from baselined findings and points at the new ones', () => {
        const output = report(
            [
                extension(project('MyPlugin'), {
                    typescript: run('failed', {
                        findings: 12,
                        newFindings: 2,
                        baselinedFindings: 10,
                        newFindingRefs: [
                            { file: 'custom/plugins/MyPlugin/src/a.ts', code: 'TS2322' },
                            { file: 'custom/plugins/MyPlugin/src/b.ts', code: 'TS2531' },
                        ],
                        output: 'custom/plugins/MyPlugin/src/a.ts(1,1): error TS2322: nope',
                    }),
                }),
            ],
            { exitCode: 1 },
        );

        expect(output).toContain('✖ 2 new · 10 baselined');
        expect(output).toContain('· 10 baselined ·');
        // The blocking findings get their own heading, one per line — the raw
        // dump below mixes them with the baselined ones in the tool's order.
        expect(output).toContain('new — must fix to pass (2):');
        expect(output).toContain('custom/plugins/MyPlugin/src/a.ts · TS2322');
        expect(output).toContain('custom/plugins/MyPlugin/src/b.ts · TS2531');
        // The dump must say what it is, or it reads as the fix list.
        expect(output).toContain('full TypeScript output (new + 10 baselined):');
    });

    // Without a baseline file the new-findings block used to be suppressed
    // entirely, so a first failing run named nothing to fix.
    it('names the blocking findings even when nothing is baselined', () => {
        const output = report(
            [
                extension(project('MyPlugin'), {
                    eslint: run('failed', {
                        findings: 1,
                        newFindings: 1,
                        baselinedFindings: 0,
                        newFindingRefs: [{ file: 'custom/plugins/MyPlugin/src/a.ts', code: 'no-console' }],
                        output: 'custom/plugins/MyPlugin/src/a.ts\n  1:1  error  Unexpected console  no-console',
                    }),
                }),
            ],
            { exitCode: 1 },
        );

        expect(output).toContain('new — must fix to pass (1):');
        expect(output).toContain('custom/plugins/MyPlugin/src/a.ts · no-console');
        // Nothing was suppressed, so labelling the dump would be noise.
        expect(output).not.toContain('full ESLint output');
    });

    it('collapses the new-finding list past ten entries', () => {
        const refs = Array.from({ length: 13 }, (_unused, index) => ({
            file: `custom/plugins/MyPlugin/src/f${index}.ts`,
            code: 'TS2322',
        }));
        const output = report(
            [
                extension(project('MyPlugin'), {
                    typescript: run('failed', { findings: 13, newFindings: 13, newFindingRefs: refs, output: 'x' }),
                }),
            ],
            { exitCode: 1 },
        );

        expect(output).toContain('new — must fix to pass (13):');
        expect(output).toContain('custom/plugins/MyPlugin/src/f9.ts · TS2322');
        expect(output).not.toContain('custom/plugins/MyPlugin/src/f10.ts');
        expect(output).toContain('… and 3 more');
    });

    it('nudges to prune stale baseline entries', () => {
        const output = report([
            extension(project('MyPlugin'), {
                typescript: run('passed', { findings: 3, newFindings: 0, baselinedFindings: 3, staleBaseline: 2 }),
            }),
        ]);

        expect(output).toContain('2 baseline entries no longer match');
        expect(output).toContain('--update-baseline');
    });

    it('lists the baselines written under --update-baseline', () => {
        const output = report([extension(project('MyPlugin'))], {
            baselineUpdates: ['custom/plugins/MyPlugin/.shopware-admin-baseline.json — 43 recorded, 2 pruned'],
        });

        expect(output).toContain('Baseline updated');
        expect(output).toContain('custom/plugins/MyPlugin/.shopware-admin-baseline.json — 43 recorded, 2 pruned');
    });

    it('keeps the plain finding wording when no baseline is in play', () => {
        const output = report(
            [
                extension(project('MyPlugin'), {
                    typescript: run('failed', { findings: 3, newFindings: 3, baselinedFindings: 0 }),
                }),
            ],
            { exitCode: 1 },
        );

        expect(output).toContain('✖ 3 finding(s)');
        expect(output).not.toContain('baselined');
    });
});
