/**
 * Validate that the lines a pull request adds to a RELEASE_INFO-*.md file land in
 * the version section named by its `milestone/X.Y.Z.P` label.
 *
 * The invariant: a release section is written by the release it announces. After
 * the `X.Y.Z.x` branch-off, new trunk entries belong to the upcoming section, not
 * to the closed `X.Y.Z.*` ones; only a backport still writes into an older line,
 * and its milestone label names the patch section its entries belong to.
 *
 * Label validity is deliberately out of scope: `milestone-label.ts` already
 * rejects a milestone on a closed line unless the matching `backport-X.Y.Z.x`
 * label routes the PR there. Both commit statuses are required, so label–branch
 * consistency there plus content–label consistency here closes the gap: an entry
 * can only land in a closed section on a PR that really backports into that line.
 */

import { MILESTONE_LABEL_PREFIX, SKIP_CHECK_LABEL as MILESTONE_SKIP_LABEL, pullRequestNumbersInMergeGroup } from './milestone-label.ts';

export const STATUS_CONTEXT = 'release-info/section';

/** Opt-out for deliberate edits of an already-released section, e.g. fixing a typo. */
export const SKIP_CHECK_LABEL = 'skip-release-info-check';

/** RELEASE_INFO files live in the repository root, one per major line. */
const RELEASE_INFO_PATTERN = /^RELEASE_INFO-\d+\.\d+\.md$/;

/** `# 6.7.14.0 (upcoming)` and `# 6.7.13.0` both open the section they name. */
const VERSION_HEADING_PATTERN = /^# (\d+\.\d+\.\d+\.\d+)\b/;

/** Fenced code blocks may quote `# ...` lines that must not open a section. */
const FENCE_PATTERN = /^ {0,3}(`{3,}|~{3,})(.*)$/;

const HUNK_HEADER_PATTERN = /^@@ -\d+(?:,\d+)? \+(\d+)(?:,\d+)? @@/;

/**
 * New-file line numbers of the lines a unified diff patch adds. The patches the
 * GitHub API returns start directly at the first `@@` hunk header.
 */
export function addedLineNumbers(patch: string): number[] {
    const added: number[] = [];
    let line = 0;

    for (const patchLine of patch.split('\n')) {
        const hunk = HUNK_HEADER_PATTERN.exec(patchLine);
        if (hunk) {
            line = Number(hunk[1]);
            continue;
        }

        if (patchLine.startsWith('+')) {
            added.push(line);
            line++;
        } else if (!patchLine.startsWith('-') && !patchLine.startsWith('\\')) {
            // Context line; removals and "\ No newline at end of file" do not
            // advance the new file.
            line++;
        }
    }

    return added;
}

/**
 * The version section each line of the file belongs to, indexed by line number - 1.
 * A heading line already belongs to the section it opens; lines above the first
 * version heading map to `null`.
 */
export function sectionByLine(content: string): (string | null)[] {
    const sections: (string | null)[] = [];
    let current: string | null = null;
    let fence: string | null = null;

    for (const line of content.split('\n')) {
        const marker = FENCE_PATTERN.exec(line);
        if (fence !== null) {
            // CommonMark: only a bare fence of the same character and at least the
            // opening length closes a block — a ```js line inside it is content.
            // Toggling on every marker misread the whole file once a fence was
            // left unclosed, which is exactly when parsing must stay right.
            if (marker && marker[1][0] === fence[0] && marker[1].length >= fence.length && marker[2].trim() === '') {
                fence = null;
            }
        } else if (marker && !(marker[1][0] === '`' && marker[2].includes('`'))) {
            // A backtick fence's info string may not contain backticks.
            fence = marker[1];
        } else {
            current = VERSION_HEADING_PATTERN.exec(line)?.[1] ?? current;
        }
        sections.push(current);
    }

    return sections;
}

export type ReleaseInfoFile = {
    filename: string;
    /** Unified diff patch; the API omits it when the file's diff is too large. */
    patch?: string;
    /** The file at the PR head; absent when the PR deletes the file. */
    headContent?: string;
};

/** `message` is the long form for the job summary, `short` the one for the commit status. */
export type ReleaseInfoVerdict = { status: 'ok' | 'skipped' | 'invalid'; message: string; short: string };

type Mismatch = { filename: string; section: string | null; lines: number };

function milestoneVersionOf(labels: string[]): string | undefined {
    const milestones = labels.filter((label) => label.startsWith(MILESTONE_LABEL_PREFIX));
    if (milestones.length !== 1) {
        return undefined;
    }

    const version = milestones[0].slice(MILESTONE_LABEL_PREFIX.length);

    return /^\d+\.\d+\.\d+\.\d+$/.test(version) ? version : undefined;
}

export function evaluateReleaseInfoSections({ labels, files }: { labels: string[]; files: ReleaseInfoFile[] }): ReleaseInfoVerdict {
    const releaseInfoFiles = files.filter((file) => RELEASE_INFO_PATTERN.test(file.filename));
    if (releaseInfoFiles.length === 0) {
        return { status: 'ok', message: 'does not change a RELEASE_INFO file', short: 'no RELEASE_INFO changes' };
    }

    if (labels.includes(SKIP_CHECK_LABEL)) {
        return { status: 'skipped', message: `\`${SKIP_CHECK_LABEL}\` is set`, short: `${SKIP_CHECK_LABEL} is set` };
    }

    // Without a trusted milestone there is no section to hold the entries against.
    if (labels.includes(MILESTONE_SKIP_LABEL)) {
        return { status: 'skipped', message: `\`${MILESTONE_SKIP_LABEL}\` is set, so no milestone names the section the entries belong to`, short: `${MILESTONE_SKIP_LABEL} is set` };
    }

    const version = milestoneVersionOf(labels);
    if (version === undefined) {
        // Missing, duplicated, or malformed labels are the milestone/label
        // check's verdict to report; repeating it here would only double the noise.
        return {
            status: 'skipped',
            message: `carries no single valid \`${MILESTONE_LABEL_PREFIX}*\` label to name the right section; the \`milestone/label\` status reports that`,
            short: 'no valid milestone label yet, see milestone/label',
        };
    }

    const mismatches: Mismatch[] = [];

    for (const file of releaseInfoFiles) {
        // An unverifiable diff must not pass silently.
        if (file.patch === undefined) {
            return {
                status: 'invalid',
                message: `changes \`${file.filename}\`, but the diff is too large for the API to return, so the sections cannot be verified — split the change`,
                short: `${file.filename} diff too large to verify`,
            };
        }

        const added = addedLineNumbers(file.patch);
        // Pure removals cannot land an entry in the wrong section, and a
        // rewritten entry shows up through its added lines anyway.
        if (added.length === 0) {
            continue;
        }

        if (file.headContent === undefined) {
            return {
                status: 'invalid',
                message: `adds lines to \`${file.filename}\`, but the file's head content is missing, so the sections cannot be verified`,
                short: `${file.filename} head content missing, cannot verify`,
            };
        }

        const sections = sectionByLine(file.headContent);
        const linesBySection = new Map<string | null, number>();
        for (const line of added) {
            const section = sections[line - 1] ?? null;
            linesBySection.set(section, (linesBySection.get(section) ?? 0) + 1);
        }

        for (const [section, lines] of linesBySection) {
            if (section !== version) {
                mismatches.push({ filename: file.filename, section, lines });
            }
        }
    }

    if (mismatches.length === 0) {
        return {
            status: 'ok',
            message: `every line it adds to a RELEASE_INFO file is in the \`${version}\` section its milestone names`,
            short: `RELEASE_INFO entries are in the ${version} section`,
        };
    }

    const details = mismatches.map(({ filename, section, lines }) =>
        section === null
            ? `adds ${lines} line(s) above the first version heading of \`${filename}\``
            : `adds ${lines} line(s) to the \`${section}\` section of \`${filename}\``);
    const sectionNames = [...new Set(mismatches.map(({ section }) => section ?? 'preamble'))].join(', ');

    return {
        status: 'invalid',
        message: `${details.join(', ')} while carrying \`${MILESTONE_LABEL_PREFIX}${version}\`. `
            + `Move the entries into the \`# ${version}\` section, or fix the milestone label if the entries are right — `
            + `writing into a released line takes a backport PR whose milestone matches, see the \`milestone/label\` check. `
            + `For a deliberate edit of a past release's section, set \`${SKIP_CHECK_LABEL}\`.`,
        short: `RELEASE_INFO entries in ${sectionNames}, but the milestone is ${version}`,
    };
}

type GithubClient = {
    rest: {
        pulls: {
            get(options: { owner: string; repo: string; pull_number: number }): Promise<{
                data: { number: number; head: { sha: string }; labels: { name: string }[] };
            }>;
            listFiles(options: { owner: string; repo: string; pull_number: number; per_page: number; page: number }): Promise<{
                data: { filename: string; status: string; patch?: string }[];
            }>;
        };
        repos: {
            getContent(options: { owner: string; repo: string; path: string; ref: string; mediaType: { format: string } }): Promise<{ data: unknown }>;
            createCommitStatus(options: {
                owner: string; repo: string; sha: string;
                state: 'success' | 'failure'; context: string; description: string; target_url?: string;
            }): Promise<unknown>;
            compareCommitsWithBasehead(options: { owner: string; repo: string; basehead: string }): Promise<{ data: { commits: { sha: string }[] } }>;
            listPullRequestsAssociatedWithCommit(options: { owner: string; repo: string; commit_sha: string }): Promise<{ data: { number: number }[] }>;
        };
    };
};

type Logger = {
    info(message: string): void;
    warning(message: string): void;
    error(message: string): void;
    setFailed(message: string): void;
    summary: {
        addRaw(text: string): { write(): Promise<unknown> };
    };
};

type Repo = { owner: string; repo: string };

type PullRequestContext = {
    eventName?: string;
    repo: Repo;
    payload: {
        pull_request?: { number: number; head: { sha: string }; labels?: { name: string }[] };
        merge_group?: { head_ref: string; base_sha: string; head_sha: string };
    };
};

type Toolkit = { github: GithubClient; core: Logger; context: PullRequestContext };

type CheckedPullRequest = { number: number; labels: string[]; headSha: string };

async function pullRequestsToCheck({ github, core, context }: Toolkit): Promise<CheckedPullRequest[]> {
    const mergeGroup = context.payload.merge_group;

    if (context.eventName !== 'merge_group' || !mergeGroup) {
        const pullRequest = context.payload.pull_request;
        if (!pullRequest) {
            throw new Error('This function can only be called on pull request events.');
        }

        return [{
            number: pullRequest.number,
            labels: (pullRequest.labels ?? []).map((label) => label.name),
            headSha: pullRequest.head.sha,
        }];
    }

    const numbers = await pullRequestNumbersInMergeGroup(github, core, context.repo, mergeGroup);

    return Promise.all(numbers.map(async (number) => {
        const { data } = await github.rest.pulls.get({ ...context.repo, pull_number: number });

        return { number: data.number, labels: data.labels.map((label) => label.name), headSha: data.head.sha };
    }));
}

/** The PR's RELEASE_INFO changes, each paired with the file as it looks at the head. */
async function releaseInfoFilesOf(github: GithubClient, repo: Repo, pullRequest: CheckedPullRequest): Promise<ReleaseInfoFile[]> {
    const files: { filename: string; status: string; patch?: string }[] = [];
    for (let page = 1; ; page++) {
        const { data } = await github.rest.pulls.listFiles({ ...repo, pull_number: pullRequest.number, per_page: 100, page });
        files.push(...data);
        if (data.length < 100) {
            break;
        }
    }

    return Promise.all(files
        .filter((file) => RELEASE_INFO_PATTERN.test(file.filename))
        .map(async (file) => ({
            filename: file.filename,
            patch: file.patch,
            headContent: file.status === 'removed' ? undefined : await rawContent(github, repo, file.filename, pullRequest.headSha),
        })));
}

async function rawContent(github: GithubClient, repo: Repo, path: string, ref: string): Promise<string | undefined> {
    const { data } = await github.rest.repos.getContent({ ...repo, path, ref, mediaType: { format: 'raw' } });

    return typeof data === 'string' ? data : undefined;
}

/** GitHub rejects a longer status description with a 422 rather than truncating it. */
const STATUS_DESCRIPTION_LIMIT = 140;

function truncate(text: string): string {
    return text.length <= STATUS_DESCRIPTION_LIMIT ? text : `${text.slice(0, STATUS_DESCRIPTION_LIMIT - 1)}…`;
}

/** The commit the status belongs to: the merge group for the queue, the head otherwise. */
function statusShaOf(context: PullRequestContext): string {
    const mergeGroup = context.payload.merge_group;
    if (context.eventName === 'merge_group' && mergeGroup) {
        return mergeGroup.head_sha;
    }

    const pullRequest = context.payload.pull_request;
    if (!pullRequest) {
        throw new Error('This function can only be called on pull request events.');
    }

    return pullRequest.head.sha;
}

/** The only link a commit status carries, so point it at the run holding the long form. */
function runUrl({ owner, repo }: Repo): string | undefined {
    const server = process.env.GITHUB_SERVER_URL;
    const runId = process.env.GITHUB_RUN_ID;

    return server && runId ? `${server}/${owner}/${repo}/actions/runs/${runId}` : undefined;
}

/**
 * Reports the verdict as a commit status instead of failing the job, for the same
 * reason `milestone-label.ts` does: the next run overwrites the status by context,
 * so fixing the label or the entries turns the pull request green without a new
 * commit.
 */
export async function checkReleaseInfoSections(toolkit: Toolkit): Promise<void> {
    const { github, core, context } = toolkit;
    const pullRequests = await pullRequestsToCheck(toolkit);

    const judged = await Promise.all(pullRequests.map(async (pullRequest) => ({
        pullRequest,
        verdict: evaluateReleaseInfoSections({
            labels: pullRequest.labels,
            files: await releaseInfoFilesOf(github, context.repo, pullRequest),
        }),
    })));

    for (const { pullRequest, verdict } of judged) {
        core.info(`PR #${pullRequest.number} ${verdict.message}`);
    }

    const summary = judged.map(({ pullRequest, verdict }) =>
        `- ${{ ok: '✅', skipped: '➖', invalid: '❌' }[verdict.status]} #${pullRequest.number} ${verdict.message}`);
    await core.summary.addRaw(`## Release info sections\n\n${summary.join('\n')}\n`).write();

    const invalid = judged.filter(({ verdict }) => verdict.status === 'invalid');

    // A single pull request speaks for itself; a batched merge group has to name the
    // offenders, because the status is the only thing the queue shows.
    const description = judged.length === 1
        ? judged[0].verdict.short
        : invalid.length === 0
            ? `all ${judged.length} queued pull requests keep their RELEASE_INFO sections consistent`
            : invalid.map(({ pullRequest, verdict }) => `#${pullRequest.number} ${verdict.short}`).join('; ');

    const target = runUrl(context.repo);
    await github.rest.repos.createCommitStatus({
        ...context.repo,
        sha: statusShaOf(context),
        state: invalid.length === 0 ? 'success' : 'failure',
        context: STATUS_CONTEXT,
        description: truncate(description),
        ...(target ? { target_url: target } : {}),
    });
}
