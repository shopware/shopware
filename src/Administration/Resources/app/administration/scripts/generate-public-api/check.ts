/**
 * @sw-package framework
 *
 * Runs the break check on commits of this repository:
 *
 *     composer admin:check-public-api -- <base> <head>
 *     composer admin:check-public-api -- --history <range> <result.json>
 *
 * The first form prints the findings of one change and fails when it has unannounced breaks. The second runs
 * the check on every commit of the range and writes the findings per commit, to measure the check itself.
 */
import fs from 'node:fs';
import { loadFeatureConfig, majorFlagsOf } from '../feature-flags';
import { createCommitCheck } from './commit-check';
import { ADMINISTRATION_ROOT } from './committed-snapshot';
import { formatFindings } from './findings';
import { repositoryOf } from './git-source-tree';

const USAGE = 'Usage: check.ts <base> <head> | check.ts --history <range> <result.json>';

const check = createCommitCheck(repositoryOf(ADMINISTRATION_ROOT), majorFlagsOf(loadFeatureConfig()));
const [first, second, third] = process.argv.slice(2);

if (first === '--history' && second !== undefined && third !== undefined) {
    const results = check.history(second);
    const unread = results.filter(({ error }) => error !== undefined).length;
    const withBreaks = results.filter(({ findings }) => findings.some(({ group }) => group === 'Break')).length;

    fs.writeFileSync(third, `${JSON.stringify(results, null, 4)}\n`);
    process.stdout.write(
        `${results.length} commits, ${withBreaks} with breaks, ${unread} that the generator could not read. Findings: ${third}\n`,
    );
} else if (first !== undefined && second !== undefined && third === undefined) {
    const findings = check.compare(first, second);

    process.stdout.write(formatFindings(findings));
    process.exitCode = findings.some(({ group }) => group === 'Break') ? 1 : 0;
} else {
    process.stderr.write(`${USAGE}\n`);
    process.exitCode = 2;
}
