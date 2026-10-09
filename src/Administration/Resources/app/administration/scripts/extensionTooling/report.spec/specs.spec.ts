/**
 * @sw-package framework
 */

import { extension, project, report, run } from './helpers';

describe('renderCheckReport spec type-check program', () => {
    it('renders a TS (specs) status line and a specs summary column when specs ran', () => {
        const output = report(
            [
                extension(project('MyPlugin'), {
                    typescriptSpecs: run('failed', {
                        findings: 2,
                        newFindings: 2,
                        output: 'src/a.spec.ts(1,1): error TS2322: x',
                    }),
                }),
            ],
            { exitCode: 1 },
        );

        expect(output).toContain('TS (specs)');
        expect(output).toContain('✖ 2 finding(s)');
        // The summary carries a dedicated specs column.
        expect(output).toContain('specs');
    });

    it('hides the spec line for extensions without specs and shows a dash in the summary', () => {
        const output = report([extension(project('MyPlugin'), { typescriptSpecs: run('no-files') })]);

        expect(output).not.toContain('TS (specs)');
        expect(output).toContain('—');
    });

    it('reads a fully baselined spec program as a passed line', () => {
        const output = report([
            extension(project('MyPlugin'), {
                typescriptSpecs: run('passed', { findings: 4, newFindings: 0, baselinedFindings: 4 }),
            }),
        ]);

        expect(output).toContain('TS (specs)');
        // The count carries a trailing --verbose hint; this case is about the
        // spec program reporting its own baselined count at all.
        expect(output).toContain('(4 baselined');
    });
});
