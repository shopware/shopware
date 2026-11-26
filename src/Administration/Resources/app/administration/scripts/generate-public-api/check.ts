/**
 * @sw-package framework
 *
 * Runs the break check on two commits of this repository:
 *
 *     composer admin:check-public-api -- [--github] [--report-only] <base> <head>
 *
 * It prints the findings of the change and fails when it has unannounced breaks.
 */
import fs from 'node:fs';
import { runCheck } from './break-check/check-command';
import { ADMINISTRATION_ROOT, currentMajorFlags } from './source/checkout';
import { repositoryOf } from './source/git-source-tree';

const { output, exitCode, jobSummary } = runCheck(process.argv.slice(2), {
    repository: repositoryOf(ADMINISTRATION_ROOT),
    majorFlags: currentMajorFlags(),
});
const jobSummaryFile = process.env.GITHUB_STEP_SUMMARY;

if (jobSummary !== undefined && jobSummaryFile !== undefined) {
    fs.appendFileSync(jobSummaryFile, jobSummary);
}

process.stdout.write(output);
process.exitCode = exitCode;
