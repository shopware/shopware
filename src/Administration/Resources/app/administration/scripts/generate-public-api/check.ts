/**
 * @sw-package framework
 *
 * Runs the break check on two commits of this repository:
 *
 *     composer admin:check-public-api -- <base> <head>
 *
 * It prints the findings of the change and fails when it has unannounced breaks.
 */
import { loadFeatureConfig, majorFlagsOf } from '../feature-flags';
import { compareCommits } from './commit-check';
import { ADMINISTRATION_ROOT } from './committed-snapshot';
import { formatFindings } from './findings';
import { repositoryOf } from './git-source-tree';

const [base, head, ...rest] = process.argv.slice(2);

if (base === undefined || head === undefined || rest.length > 0) {
    process.stderr.write('Usage: composer admin:check-public-api -- <base> <head>\n');
    process.exitCode = 2;
} else {
    const findings = compareCommits(repositoryOf(ADMINISTRATION_ROOT), majorFlagsOf(loadFeatureConfig()), base, head);

    process.stdout.write(formatFindings(findings));
    process.exitCode = findings.some(({ group }) => group === 'Break') ? 1 : 0;
}
