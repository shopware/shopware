/**
 * @sw-package framework
 */

import { extension, project, report, run } from './helpers';

describe('renderCheckReport findings baseline', () => {
    it('reads a fully baselined tool as a green pass with the baselined count', () => {
        const output = report([
            extension(project('MyPlugin'), {
                typescript: run('passed', { findings: 5, newFindings: 0, baselinedFindings: 5 }),
            }),
        ]);

        expect(output).toContain('✔ passed');
        expect(output).toContain('(5 baselined)');
        // A fully baselined pass suppresses the raw diagnostics like any pass.
        expect(output).not.toContain('exit 1');
        expect(output).toContain('5 baselined');
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
        expect(output).toContain('new (not baselined): custom/plugins/MyPlugin/src/a.ts · TS2322');
        expect(output).toContain('· 10 baselined ·');
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
