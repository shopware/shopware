/**
 * Precompute the sw-review change profile in CI, before the agent starts.
 *
 * Runs as a gh aw `steps:` entry in the agent job: after checkout, outside the agent
 * sandbox, with the job's GITHUB_TOKEN. The agent sandbox cannot execute repository
 * scripts or redirect output, so the deterministic classification happens here and the
 * orchestrator only reads the result with `cat`.
 *
 * The step runs on the host before gh aw restores `.agents` from the base branch, so the
 * workflow extracts this file and classify.ts from TRUSTED_REF (the base branch head, or
 * the default branch for `/sw-review` comment runs) into $RUNNER_TEMP and runs them from
 * there, never from the checked-out PR head. The router index and the guides are read
 * from the same commit, so a pull request cannot change the rules it is reviewed against.
 *
 *   PR_NUMBER=123 GITHUB_REPOSITORY=owner/repo GH_TOKEN=... TRUSTED_REF=<sha> \
 *     node ci-profile.ts [out-file]
 *
 * Writes <out-file> (default .sw-review/profile.json) with either the profile plus the
 * PR's CI state, or {"available": false, "reason": "..."} when the PR cannot be
 * classified (unreadable PR, no merge base, no router index at TRUSTED_REF).
 */

import { execFileSync } from 'node:child_process';
import { appendFileSync, mkdirSync, realpathSync, writeFileSync } from 'node:fs';
import { dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { classify, collectRange, loadRulesFromRef, type Meta, type Profile } from './classify.ts';

// The REST pull request object (`gh api repos/{repo}/pulls/{n}`): `gh pr view --json` has
// no author-association field. `head.repo` is null when the fork was deleted.
export type PullRequest = {
    base: { ref: string; repo: { full_name: string } };
    head: { repo: { full_name: string } | null };
    author_association: string;
    labels: Array<{ name: string }>;
    body: string | null;
};

export type CheckRun = {
    name: string;
    bucket: string;
};

export type CiState = {
    checks: number;
    failed: string[];
    pending: number;
};

export type CiProfile = Profile & { ci: CiState };

type Env = Record<string, string | undefined>;

// "fixes #123", "Fixes: #123", "closed #1", "resolves #9". The PR body is untrusted text;
// this is the one thing read from it, and it only toggles the fix_without_test signal.
const FIXES_ISSUE = /\b(fix(es|ed)?|close[sd]?|resolve[sd]?)\s*:?\s+#[0-9]+/i;

export function metaFromPullRequest(pr: PullRequest): Meta {
    return {
        fork: pr.head.repo === null || pr.head.repo.full_name !== pr.base.repo.full_name,
        author_association: pr.author_association,
        labels: pr.labels.map((label) => label.name),
        fixes_issue: FIXES_ISSUE.test(pr.body ?? ''),
    };
}

// CI state of the PR head, so workers know whether the PR's own tests ran and passed.
// A failed job that crashed before the suite ran hides every other result; the review must
// say "test results unavailable" instead of trusting a green-looking diff.
export function ciState(checks: CheckRun[]): CiState {
    return {
        checks: checks.length,
        failed: checks.filter((check) => check.bucket === 'fail').map((check) => check.name),
        pending: checks.filter((check) => check.bucket === 'pending').length,
    };
}

export function stepSummary(profile: CiProfile, base: string, mergeBase: string, rulesRef: string): string {
    const guides = profile.guides
        .map((guide) => guide.guide + (guide.file_exists ? '' : ' (missing)'))
        .join(', ');
    const cap = profile.size.over_cap ? ' (over cap: split slices, never skip)' : '';
    const failed = profile.ci.failed.length > 0 ? profile.ci.failed.join(', ') : 'none';

    return [
        '### sw-review change profile',
        '',
        `scripts and rules: \`${rulesRef}\`; base: \`${base}\`; merge base: \`${mergeBase}\``,
        '',
        `signals: ${profile.signals.join(', ')}`,
        '',
        `guides: ${guides}`,
        '',
        `size: ${profile.size.files} files, ${profile.size.changed_lines} changed lines${cap}; `
            + `base commits merged in: ${profile.merged_from_base.length}; CI failed checks: ${failed}`,
        '',
    ].join('\n');
}

function git(root: string, args: string[]): string {
    return execFileSync('git', ['-C', root, ...args], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
}

// `gh pr checks` exits 8 while checks are pending and 1 when one failed, with the JSON
// already on stdout. A non-zero exit is only an error when there is nothing to parse.
function ghJson<T>(args: string[]): T {
    let stdout: string;
    try {
        stdout = execFileSync('gh', args, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
    } catch (error) {
        stdout = String((error as { stdout?: string }).stdout ?? '');
    }

    return JSON.parse(stdout) as T;
}

function required(env: Env, name: string): string {
    const value = env[name];
    if (!value) {
        throw new Error(`${name} is required`);
    }

    return value;
}

export function main(argv: string[], env: Env): number {
    const prNumber = required(env, 'PR_NUMBER');
    const repo = required(env, 'GITHUB_REPOSITORY');
    const trustedRef = required(env, 'TRUSTED_REF');
    const out = argv[0] ?? '.sw-review/profile.json';
    const root = process.cwd();
    mkdirSync(dirname(out), { recursive: true });

    const summary = (text: string): void => {
        if (env.GITHUB_STEP_SUMMARY) {
            appendFileSync(env.GITHUB_STEP_SUMMARY, text);
        }
    };
    const unavailable = (reason: string): number => {
        writeFileSync(out, `${JSON.stringify({ available: false, reason })}\n`);
        console.log(`sw-review profile: unavailable (${reason})`);
        summary(`### sw-review change profile\n\nunavailable: ${reason}\n`);

        return 0;
    };

    let pr: PullRequest;
    try {
        pr = ghJson<PullRequest>(['api', `repos/${repo}/pulls/${prNumber}`]);
    } catch {
        return unavailable(`could not read PR #${prNumber}`);
    }

    const base = pr.base.ref;
    try {
        git(root, ['fetch', '--no-tags', 'origin', base]);
    } catch {
        return unavailable(`could not fetch base branch ${base}`);
    }

    let mergeBase: string;
    try {
        mergeBase = git(root, ['merge-base', `origin/${base}`, 'HEAD']).trim();
    } catch {
        return unavailable(`no merge base with origin/${base}`);
    }

    const rules = loadRulesFromRef(root, trustedRef);
    if (rules === null) {
        return unavailable(`${trustedRef} has no .agents/skills/sw-review/guides/index.json`);
    }

    const { files, diff, mergedFromBase } = collectRange(root, `${mergeBase}...HEAD`);
    const profile = classify({ files, diff, base, meta: metaFromPullRequest(pr), root, rules, mergedFromBase });

    let checks: CheckRun[] = [];
    try {
        checks = ghJson<CheckRun[]>(['pr', 'checks', prNumber, '--repo', repo, '--json', 'name,state,bucket']);
    } catch {
        checks = [];
    }

    const result: CiProfile = { ...profile, ci: ciState(checks) };
    writeFileSync(out, `${JSON.stringify(result, null, 2)}\n`);
    summary(stepSummary(result, base, mergeBase, trustedRef));
    console.log(`sw-review profile written to ${out} (${result.guides.length} guides, scripts and rules from ${trustedRef})`);

    return 0;
}

function isEntryPoint(): boolean {
    try {
        return process.argv[1] !== undefined && realpathSync(process.argv[1]) === fileURLToPath(import.meta.url);
    } catch {
        return false;
    }
}

if (isEntryPoint()) {
    process.exitCode = main(process.argv.slice(2), process.env);
}
