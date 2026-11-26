/**
 * @sw-package framework
 *
 * The command behind `composer admin:check-public-api`: reads the arguments, runs the break check on two commits
 * and decides the exit code.
 */
import { parseArgs } from 'node:util';
import type { GitRepository } from '../source/git-source-tree';
import { compareCommits } from './commit-check';
import { breaksOf } from './finding';
import { printAnnotations, printJobSummary } from './github-report';
import type { AnnotationLevel } from './github-report';
import { printFindings } from './print-findings';

/** @private */
export const CHECK_USAGE = 'Usage: composer admin:check-public-api -- [--github] [--report-only] <base> <head>\n';

/** @private */
export interface CheckContext {
    repository: GitRepository;
    majorFlags: readonly string[];
}

/**
 * @private
 *
 * `jobSummary` is the Markdown for the summary of a GitHub Actions job, only with `--github`.
 */
export interface CheckResult {
    output: string;
    exitCode: number;
    jobSummary?: string;
}

interface CheckOptions {
    base: string;
    head: string;
    github: boolean;
    level: AnnotationLevel;
}

function optionsOf(args: string[]): CheckOptions | null {
    try {
        const { values, positionals } = parseArgs({
            args,
            allowPositionals: true,
            options: {
                github: { type: 'boolean', default: false },
                'report-only': { type: 'boolean', default: false },
            },
        });
        const [base, head] = positionals;

        return positionals.length === 2 && base !== undefined && head !== undefined
            ? { base, head, github: values.github, level: values['report-only'] ? 'warning' : 'error' }
            : null;
    } catch {
        return null;
    }
}

/**
 * @private
 *
 * `--github` adds an annotation per break and the summary of the job. `--report-only` keeps the exit code at 0
 * when the change has unannounced breaks, so the check can run on pull requests before it may block them. A
 * check that cannot read a commit still fails.
 */
export function runCheck(args: string[], { repository, majorFlags }: CheckContext): CheckResult {
    const options = optionsOf(args);

    if (options === null) {
        return { output: CHECK_USAGE, exitCode: 2 };
    }

    const { base, head, github, level } = options;
    const findings = compareCommits(repository, majorFlags, base, head);
    const exitCode = breaksOf(findings).length > 0 && level === 'error' ? 1 : 0;

    if (!github) {
        return { output: printFindings(findings), exitCode };
    }

    return {
        output: `${printFindings(findings)}${printAnnotations(findings, level, repository.administration)}`,
        exitCode,
        jobSummary: printJobSummary(findings, level),
    };
}
