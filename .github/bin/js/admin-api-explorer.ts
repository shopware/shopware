/**
 * CI glue for the Admin API Explorer report (admin-api-explorer.yml and
 * admin-api-explorer-comment.yml).
 *
 * `verify` is the guard of the pull request job: the report command can exit 0 after
 * extracting nothing, or after comparing the wrong commits, so the job asserts what the run
 * produced instead of its exit code.
 *
 * `relayComment` runs in the privileged workflow_run job. The relayed artifact is written by
 * a job a pull request (also from a fork) can change, so it carries data only, never
 * Markdown: the number is parsed strictly and must belong to the pull request whose head the
 * run built, `comment-data.json` must match its schema exactly, and the comment is rendered
 * here with every string from it inside a code span, so it cannot carry links, HTML or
 * mentions into a comment posted as github-actions[bot]. Links come from the event only.
 */

import { existsSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';

export const COMMENT_MARKER = '<!-- admin-api-explorer-report -->';

export const MAX_DATA_BYTES = 128 * 1024;
export const MAX_LIST_ENTRIES = 50;

export const EXIT_OK = 0;
export const EXIT_BREAKING = 3;

const REPORT_FILES = ['index.html', 'comment.md', 'comment-data.json', 'diff.json', 'summary.json'] as const;

const BOT_LOGIN = 'github-actions[bot]';

const SHA_PATTERN = /^[0-9a-f]{40}$/;

const CONTROL_CHARACTERS = new RegExp('[\\u0000-\\u001f\\u007f-\\u009f\\u2028\\u2029]');

export const COUNT_KEYS = ['breaking', 'potentiallyBreaking', 'compatible', 'added', 'newlyDeprecated', 'removedDeprecated', 'private'] as const;

const COUNT_LABELS: Record<(typeof COUNT_KEYS)[number], string> = {
    breaking: 'Breaking',
    potentiallyBreaking: 'Potentially breaking',
    compatible: 'Compatible',
    added: 'Added',
    newlyDeprecated: 'Newly deprecated',
    removedDeprecated: 'Removed deprecated',
    private: 'Private',
};

export const PRIVATE_COUNT_KEYS = ['breaking', 'potentiallyBreaking', 'compatible', 'added', 'newlyDeprecated', 'removedDeprecated'] as const;

const PRIVATE_COUNT_LABELS: Record<(typeof PRIVATE_COUNT_KEYS)[number], string> = {
    breaking: 'breaking',
    potentiallyBreaking: 'potentially breaking',
    compatible: 'compatible',
    added: 'added',
    newlyDeprecated: 'newly deprecated',
    removedDeprecated: 'removed (was deprecated)',
};

const ENTRY_LIMITS = { kind: 40, owner: 200, member: 300, reason: 300 } as const;

export type CommentSide = { ref: string; commit: string };
export type CommentEntry = { kind: string; owner: string; member: string; reason: string };
export type CommentData = {
    version: 1 | 2;
    base: CommentSide;
    head: CommentSide;
    counts: Record<(typeof COUNT_KEYS)[number], number>;
    breaking: CommentEntry[];
    potentiallyBreaking: CommentEntry[];
    omitted: { breaking: number; potentiallyBreaking: number };
    /** Only in version 2, where it is required. */
    privateCounts?: Record<(typeof PRIVATE_COUNT_KEYS)[number], number>;
};

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function isCount(value: unknown): value is number {
    return typeof value === 'number' && Number.isSafeInteger(value) && value >= 0;
}

function isPositiveInteger(value: unknown): boolean {
    return typeof value === 'number' && Number.isInteger(value) && value > 0;
}

function checkKeys(path: string, value: Record<string, unknown>, keys: readonly string[], problems: string[]): void {
    for (const key of Object.keys(value)) {
        if (!keys.includes(key)) {
            problems.push(`${path}.${key} is not allowed`);
        }
    }
    for (const key of keys) {
        if (!(key in value)) {
            problems.push(`${path}.${key} is missing`);
        }
    }
}

function checkString(path: string, value: unknown, maxLength: number, problems: string[]): void {
    if (typeof value !== 'string') {
        problems.push(`${path} is not a string`);
    } else if (value.length === 0 || value.length > maxLength) {
        problems.push(`${path} must have 1 to ${maxLength} characters, has ${value.length}`);
    } else if (CONTROL_CHARACTERS.test(value)) {
        problems.push(`${path} contains control characters`);
    }
}

/** Returns every way `value` differs from the comment-data.json schema; empty when it matches exactly. */
export function validateCommentData(value: unknown): string[] {
    const problems: string[] = [];
    if (!isRecord(value)) {
        return ['comment data is not an object'];
    }

    const topLevelKeys = ['version', 'base', 'head', 'counts', 'breaking', 'potentiallyBreaking', 'omitted'];
    checkKeys('data', value, value.version === 2 ? [...topLevelKeys, 'privateCounts'] : topLevelKeys, problems);
    if (value.version !== 1 && value.version !== 2) {
        problems.push(`data.version is ${JSON.stringify(value.version)}, expected 1 or 2`);
    }

    for (const sideName of ['base', 'head'] as const) {
        const side = value[sideName];
        if (!isRecord(side)) {
            problems.push(`data.${sideName} is not an object`);
            continue;
        }
        checkKeys(`data.${sideName}`, side, ['ref', 'commit'], problems);
        checkString(`data.${sideName}.ref`, side.ref, 100, problems);
        if (typeof side.commit !== 'string' || !SHA_PATTERN.test(side.commit)) {
            problems.push(`data.${sideName}.commit is not a 40 character hex sha`);
        }
    }

    if (!isRecord(value.counts)) {
        problems.push('data.counts is not an object');
    } else {
        checkKeys('data.counts', value.counts, COUNT_KEYS, problems);
        for (const key of COUNT_KEYS) {
            if (key in value.counts && !isCount(value.counts[key])) {
                problems.push(`data.counts.${key} is not a non-negative integer`);
            }
        }
    }

    for (const listName of ['breaking', 'potentiallyBreaking'] as const) {
        const list = value[listName];
        if (!Array.isArray(list)) {
            problems.push(`data.${listName} is not an array`);
            continue;
        }
        if (list.length > MAX_LIST_ENTRIES) {
            problems.push(`data.${listName} has ${list.length} entries, at most ${MAX_LIST_ENTRIES} are allowed`);
            continue;
        }
        list.forEach((entry: unknown, index: number) => {
            const path = `data.${listName}[${index}]`;
            if (!isRecord(entry)) {
                problems.push(`${path} is not an object`);
                return;
            }
            checkKeys(path, entry, Object.keys(ENTRY_LIMITS), problems);
            for (const [key, limit] of Object.entries(ENTRY_LIMITS)) {
                checkString(`${path}.${key}`, entry[key], limit, problems);
            }
        });
    }

    if (!isRecord(value.omitted)) {
        problems.push('data.omitted is not an object');
    } else {
        checkKeys('data.omitted', value.omitted, ['breaking', 'potentiallyBreaking'], problems);
        for (const key of ['breaking', 'potentiallyBreaking'] as const) {
            if (key in value.omitted && !isCount(value.omitted[key])) {
                problems.push(`data.omitted.${key} is not a non-negative integer`);
            }
        }
    }

    if (value.version === 2 && 'privateCounts' in value) {
        checkPrivateCounts(value.privateCounts, isRecord(value.counts) ? value.counts.private : undefined, problems);
    }

    return problems;
}

function checkPrivateCounts(privateCounts: unknown, total: unknown, problems: string[]): void {
    if (!isRecord(privateCounts)) {
        problems.push('data.privateCounts is not an object');
        return;
    }

    checkKeys('data.privateCounts', privateCounts, PRIVATE_COUNT_KEYS, problems);
    let sum = 0;
    let complete = true;
    for (const key of PRIVATE_COUNT_KEYS) {
        const count = privateCounts[key];
        if (!isCount(count)) {
            complete = false;
            if (key in privateCounts) {
                problems.push(`data.privateCounts.${key} is not a non-negative integer`);
            }
            continue;
        }
        sum += count;
    }

    if (complete && isCount(total) && sum !== total) {
        problems.push(`data.privateCounts add up to ${sum}, but data.counts.private is ${total}`);
    }
}

/** Inline code renders no links, HTML or mentions; a backtick is the only way out of it. */
export function codeSpan(text: string): string {
    return `\`${text.replaceAll('`', "'")}\``;
}

function renderEntries(entries: CommentEntry[], omitted: number): string[] {
    const lines = entries.map((entry) => `- ${codeSpan(entry.kind)} ${codeSpan(entry.owner)} ${codeSpan(entry.member)}: ${codeSpan(entry.reason)}`);
    if (omitted > 0) {
        lines.push(`- … and ${omitted} more`);
    }
    return lines;
}

/** `reportUrl` must come from the event, never from the artifact. */
export function renderComment(data: CommentData, reportUrl: string): string {
    const side = (name: string, value: CommentSide) => `${name} ${codeSpan(value.ref)} (${codeSpan(value.commit.slice(0, 10))})`;

    const lines = [
        COMMENT_MARKER,
        '## Admin API Explorer',
        '',
        `Compared ${side('base', data.base)} with ${side('head', data.head)}.`,
        '',
        '| Change | Count |',
        '| --- | ---: |',
        ...COUNT_KEYS.map((key) => `| ${COUNT_LABELS[key]} | ${data.counts[key]} |`),
        '',
    ];

    const { privateCounts } = data;
    if (data.version === 2 && privateCounts && data.counts.private > 0) {
        const parts = PRIVATE_COUNT_KEYS.map((key) => `${privateCounts[key]} ${PRIVATE_COUNT_LABELS[key]}`);
        lines.push(`**Private API** (not a public contract, never turns this check red): ${parts.join(' · ')}`, '');
    }

    if (data.breaking.length > 0 || data.omitted.breaking > 0) {
        lines.push('### Breaking changes', '', ...renderEntries(data.breaking, data.omitted.breaking), '');
    } else {
        lines.push('No breaking changes.', '');
    }

    if (data.potentiallyBreaking.length > 0 || data.omitted.potentiallyBreaking > 0) {
        lines.push(
            '<details>',
            `<summary>Potentially breaking changes (${data.counts.potentiallyBreaking})</summary>`,
            '',
            ...renderEntries(data.potentiallyBreaking, data.omitted.potentiallyBreaking),
            '',
            '</details>',
            '',
        );
    }

    lines.push(
        `The full report is the \`api-explorer-report\` artifact of [this workflow run](${reportUrl}).`,
        'Run it locally with `composer admin:api-explorer -- --port 5175`.',
        '',
        'This check is not required, so the pull request can still be merged when a break is intended.',
        '',
    );

    return lines.join('\n');
}

type SummarySide = { commit?: unknown; components?: unknown; objectNodes?: unknown };

export type ExpectedCommits = { base: string; head: string };

/** Returns the reasons the report cannot be trusted; empty when it proves the comparison of the expected commits ran. */
export function verifyReport(dir: string, exitCode: number, expected: ExpectedCommits): string[] {
    if (exitCode !== EXIT_OK && exitCode !== EXIT_BREAKING) {
        return [`the report command exited with ${exitCode}, expected ${EXIT_OK} or ${EXIT_BREAKING}`];
    }

    const missing = REPORT_FILES.filter((file) => !existsSync(join(dir, file)) || statSync(join(dir, file)).size === 0);
    if (missing.length > 0) {
        return missing.map((file) => `${file} is missing or empty in ${dir}`);
    }

    let summary: { hasBreaking?: unknown; base?: SummarySide; head?: SummarySide };
    try {
        summary = JSON.parse(readFileSync(join(dir, 'summary.json'), 'utf8'));
    } catch (error) {
        return [`summary.json is not valid JSON: ${(error as Error).message}`];
    }

    const problems: string[] = [];

    for (const side of ['base', 'head'] as const) {
        for (const count of ['components', 'objectNodes'] as const) {
            const value = summary?.[side]?.[count];
            if (!isPositiveInteger(value)) {
                problems.push(`summary.json ${side}.${count} is ${JSON.stringify(value)}, so nothing was extracted for the ${side}`);
            }
        }

        const commit = summary?.[side]?.commit;
        if (commit !== expected[side]) {
            problems.push(`summary.json ${side}.commit is ${JSON.stringify(commit)}, but the workflow checked out ${expected[side]}`);
        }
    }

    if (typeof summary?.hasBreaking !== 'boolean') {
        problems.push('summary.json hasBreaking is not a boolean');
    } else if (summary.hasBreaking !== (exitCode === EXIT_BREAKING)) {
        problems.push(`summary.json hasBreaking is ${summary.hasBreaking}, but the command exited with ${exitCode}`);
    }

    const firstLine = readFileSync(join(dir, 'comment.md'), 'utf8').split('\n', 1)[0].trim();
    if (firstLine !== COMMENT_MARKER) {
        problems.push(`comment.md does not start with ${COMMENT_MARKER}`);
    }

    try {
        problems.push(...readCommentData(join(dir, 'comment-data.json')).problems.map((problem) => `comment-data.json: ${problem}`));
    } catch (error) {
        problems.push(`comment-data.json: ${(error as Error).message}`);
    }

    return problems;
}

function readCommentData(file: string): { data: unknown; problems: string[] } {
    const size = statSync(file).size;
    if (size > MAX_DATA_BYTES) {
        return { data: undefined, problems: [`has ${size} bytes, the limit is ${MAX_DATA_BYTES}`] };
    }

    let data: unknown;
    try {
        data = JSON.parse(readFileSync(file, 'utf8'));
    } catch (error) {
        return { data: undefined, problems: [`is not valid JSON: ${(error as Error).message}`] };
    }

    return { data, problems: validateCommentData(data) };
}

export type RelayPayload = { number: number; data: CommentData };

/** Reads the relayed artifact; throws unless it is a plain number and comment data matching the schema exactly. */
export function readRelayPayload(dir: string): RelayPayload {
    const rawNumber = readFileSync(join(dir, 'pr-number.txt'), 'utf8').trim();
    if (!/^[1-9]\d{0,9}$/.test(rawNumber)) {
        throw new Error(`pr-number.txt is not a pull request number: "${rawNumber.slice(0, 40)}"`);
    }

    const { data, problems } = readCommentData(join(dir, 'comment-data.json'));
    if (problems.length > 0) {
        throw new Error(`comment-data.json is rejected: ${problems.slice(0, 10).join('; ')}`);
    }

    return { number: Number(rawNumber), data: data as CommentData };
}

export type WorkflowRun = {
    id: number;
    html_url: string;
    head_sha: string;
    head_repository?: { full_name: string } | null;
    pull_requests?: { number: number }[] | null;
};

export type PullRequest = {
    number: number;
    state: string;
    head: { sha: string; repo: { full_name: string } | null };
};

export type RunMatch = 'match' | 'stale' | 'foreign';

/**
 * `pull_requests` is empty for fork pull requests, so the head commit is what ties the
 * relayed number to the run. A different head on the named pull request means either a
 * newer push (its own run will comment) or a forged number; neither is commented on.
 */
export function matchRunToPullRequest(run: WorkflowRun, pullRequest: PullRequest): RunMatch {
    const listed = run.pull_requests ?? [];
    if (listed.length > 0 && !listed.some((candidate) => candidate.number === pullRequest.number)) {
        return 'foreign';
    }

    if (run.head_repository && pullRequest.head.repo && run.head_repository.full_name !== pullRequest.head.repo.full_name) {
        return 'foreign';
    }

    return pullRequest.head.sha === run.head_sha ? 'match' : 'stale';
}

export type IssueComment = { id: number; body?: string | null; user?: { login: string } | null };

export function findReportComment(comments: IssueComment[]): IssueComment | undefined {
    return comments.find((comment) => comment.user?.login === BOT_LOGIN && (comment.body ?? '').startsWith(COMMENT_MARKER));
}

type Repo = { owner: string; repo: string };

export type RelayClient = {
    paginate<T>(route: unknown, options: object): Promise<T[]>;
    rest: {
        actions: {
            listWorkflowRunArtifacts: unknown;
        };
        pulls: {
            get(options: Repo & { pull_number: number }): Promise<{ data: PullRequest }>;
        };
        issues: {
            listComments: unknown;
            createComment(options: Repo & { issue_number: number; body: string }): Promise<unknown>;
            updateComment(options: Repo & { comment_id: number; body: string }): Promise<unknown>;
        };
    };
};

export type RelayCore = {
    info(message: string): void;
    notice(message: string): void;
};

export type RelayContext = {
    repo: Repo;
    payload: { workflow_run?: WorkflowRun };
};

export type RelayResult = 'created' | 'updated' | 'skipped';

/**
 * The pull request job uploads the comment data only after its guard passed, so a run that
 * was skipped (docs-only change) or broke before that legitimately has none.
 */
export async function hasCommentArtifact({ github, context }: { github: RelayClient; context: RelayContext }, name: string): Promise<boolean> {
    const run = context.payload.workflow_run;
    if (!run) {
        throw new Error('hasCommentArtifact needs a workflow_run event');
    }

    const artifacts = await github.paginate<{ name: string; expired?: boolean }>(github.rest.actions.listWorkflowRunArtifacts, {
        ...context.repo,
        run_id: run.id,
        per_page: 100,
    });

    return artifacts.some((artifact) => artifact.name === name && !artifact.expired);
}

export async function relayComment({ github, core, context }: { github: RelayClient; core: RelayCore; context: RelayContext }, dir: string): Promise<RelayResult> {
    const run = context.payload.workflow_run;
    if (!run) {
        throw new Error('relayComment needs a workflow_run event');
    }

    const { number, data } = readRelayPayload(dir);
    const { data: pullRequest } = await github.rest.pulls.get({ ...context.repo, pull_number: number });

    const match = matchRunToPullRequest(run, pullRequest);
    if (match === 'foreign') {
        throw new Error(`Run ${run.id} did not build pull request #${number}; refusing to comment`);
    }
    if (match === 'stale') {
        core.notice(`Pull request #${number} moved on from ${run.head_sha}; the run for its current head comments instead`);
        return 'skipped';
    }
    if (pullRequest.state !== 'open') {
        core.notice(`Pull request #${number} is ${pullRequest.state}; not commenting`);
        return 'skipped';
    }

    const body = renderComment(data, run.html_url);
    const comments = await github.paginate<IssueComment>(github.rest.issues.listComments, { ...context.repo, issue_number: number, per_page: 100 });
    const existing = findReportComment(comments);

    if (existing) {
        await github.rest.issues.updateComment({ ...context.repo, comment_id: existing.id, body });
        core.info(`Updated comment ${existing.id} on pull request #${number}`);
        return 'updated';
    }

    await github.rest.issues.createComment({ ...context.repo, issue_number: number, body });
    core.info(`Created the report comment on pull request #${number}`);
    return 'created';
}

function main(): void {
    const [command, dir, exitCode, base, head] = process.argv.slice(2);
    if (command !== 'verify' || !dir || !/^\d+$/.test(exitCode ?? '') || !SHA_PATTERN.test(base ?? '') || !SHA_PATTERN.test(head ?? '')) {
        console.error('usage: node admin-api-explorer.ts verify <report-dir> <report-exit-code> <base-sha> <head-sha>');
        process.exit(2);
    }

    const problems = verifyReport(dir, Number(exitCode), { base, head });
    if (problems.length > 0) {
        for (const problem of problems) {
            console.error(`::error::${problem}`);
        }
        process.exit(1);
    }

    console.log(`The report in ${dir} compares ${base} with ${head}, and both sides are non-empty`);
}

if (import.meta.url === `file://${process.argv[1]}`) {
    main();
}
