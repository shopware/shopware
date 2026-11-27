/**
 * @sw-package framework
 *
 * Runs the command behind `composer admin:check-public-api` on the commits of a small repository that the tests
 * create, as a pull request in GitHub Actions does.
 */
import { CARD_WITHOUT_TITLE, cardFile, cardTemplate, createExampleRepository } from '../testing/example-repository';
import type { ExampleRepository } from '../testing/example-repository';
import { CHECK_USAGE, runCheck } from './check-command';
import type { CheckResult } from './check-command';

const TITLE_REMOVED = [
    `::error file=${cardFile('sw-example-card.html.twig')},line=1,title=Public API%3A block_missing::`,
    'The block is gone',
].join('');

describe('scripts/generate-public-api/break-check/check-command', () => {
    let example: ExampleRepository;
    let base: string;
    let withoutTitle: string;
    let unchanged: string;

    function check(...args: string[]): CheckResult {
        return runCheck(args, { repository: example.repository, majorFlags: ['v6.8.0.0'] });
    }

    beforeEach(() => {
        example = createExampleRepository('public-api-check-command-');
        base = example.git('rev-parse', 'HEAD');
        withoutTitle = example.commit('feat: remove the title', cardTemplate(CARD_WITHOUT_TITLE));
        unchanged = example.commit('docs: add a readme', { 'README.md': 'Example' });
    });

    afterEach(() => {
        example.remove();
    });

    it('prints the summary and fails on an unannounced break', () => {
        const { output, exitCode, jobSummary } = check(base, withoutTitle);

        expect(exitCode).toBe(1);
        expect(output).toMatch(/^Public API check failed: 1 unannounced break\.\n/);
        expect(output).not.toContain('::');
        expect(jobSummary).toBeUndefined();
    });

    it('passes a change without breaks', () => {
        expect(check(withoutTitle, unchanged)).toEqual({
            output: 'Public API check passed: no breaks.\n',
            exitCode: 0,
        });
    });

    it('annotates each break on its line in the repository and adds the summary of the job', () => {
        const { output, exitCode, jobSummary } = check('--github', base, withoutTitle);

        expect(exitCode).toBe(1);
        expect(output.split('\n').filter((line) => line.startsWith('::'))).toEqual([
            expect.stringContaining(TITLE_REMOVED),
        ]);
        expect(jobSummary).toContain('```text\nPublic API check failed: 1 unannounced break.\n');
        expect(jobSummary).not.toContain('Report only');
    });

    it('only reports breaks with --report-only, as warnings', () => {
        const { output, exitCode, jobSummary } = check('--github', '--report-only', base, withoutTitle);

        expect(exitCode).toBe(0);
        expect(output).toContain(TITLE_REMOVED.replace('::error ', '::warning '));
        expect(jobSummary).toContain('Report only: unannounced breaks do not fail this check yet.');
    });

    it('still fails with --report-only when it cannot read a commit', () => {
        expect(() => check('--github', '--report-only', base, 'no-such-commit')).toThrow();
    });

    it.each([
        [[]],
        [['HEAD']],
        [['HEAD', 'HEAD', 'HEAD']],
        [['--unknown', 'HEAD', 'HEAD']],
    ])('prints the usage for the arguments %j', (args) => {
        expect(check(...args)).toEqual({ output: CHECK_USAGE, exitCode: 2 });
    });
});
