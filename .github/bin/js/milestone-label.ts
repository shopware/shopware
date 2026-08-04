/**
 * Validate and repair the `milestone/*` label of a pull request.
 *
 * The invariant: once branch `X.Y.Z.x` exists, the `X.Y.Z.*` line is closed and a PR
 * targeting the default branch must not claim it — unless it carries the matching
 * `backport-X.Y.Z.x` label, which routes it into that branch on purpose.
 *
 * Labels go stale silently because GitHub retargets a stacked PR to trunk once its
 * parent merges without emitting a `pull_request` event, so nothing re-evaluates it.
 */

export const MILESTONE_LABEL_PREFIX = 'milestone/';

/** Opt-out for the deliberate exceptions the release owner makes. */
export const SKIP_CHECK_LABEL = 'skip-milestone-check';

/** Narrows the ref search: ~700 branches would otherwise cost eight pages per event. Widen for a `7.` series. */
export const VERSION_BRANCH_QUERY = '6.';

/** Matches a version branch and captures its release line: `6.7.13.x` -> `6.7.13`, `6.6.x` -> `6.6`. */
const VERSION_BRANCH_PATTERN = /^(\d+\.\d+(?:\.\d+)*)\.x$/;

/** Matches a full four-segment version as used in milestone labels, e.g. `6.7.13.0`. */
const MILESTONE_VERSION_PATTERN = /^\d+\.\d+\.\d+\.\d+$/;

export type MilestoneVerdict =
    | { status: 'ok'; message: string }
    | { status: 'skipped'; message: string }
    | { status: 'invalid'; message: string; expected?: string };

export type MilestoneLabelInput = {
    baseRefName: string;
    defaultBranch: string;
    labels: string[];
    /** Existing version branches, e.g. `['6.6.x', '6.7.12.x', '6.7.13.x']`. */
    versionBranches: string[];
    /** A missing label is tolerated on drafts: authors drop it deliberately, and the labeler restores it on `ready_for_review`. */
    isDraft?: boolean;
};

function releaseLineOf(branch: string): string | undefined {
    return VERSION_BRANCH_PATTERN.exec(branch)?.[1];
}

function milestoneVersionOf(label: string): string | undefined {
    const version = label.slice(MILESTONE_LABEL_PREFIX.length);

    return MILESTONE_VERSION_PATTERN.test(version) ? version : undefined;
}

/**
 * The patch after the highest already-branched minor. Derived from branches, not the
 * latest tag, so it stays correct between a branch-off and its release — the window
 * where labels go wrong.
 */
export function expectedMilestone(versionBranches: string[]): string | undefined {
    let highest: number[] | undefined;

    for (const branch of versionBranches) {
        const segments = releaseLineOf(branch)?.split('.').map(Number);
        // Only three-segment lines (6.7.13.x) name a minor; 6.6.x names a whole major.
        if (!segments || segments.length !== 3 || segments.some((segment) => !Number.isInteger(segment))) {
            continue;
        }
        if (!highest || compareSegments(segments, highest) > 0) {
            highest = segments;
        }
    }

    return highest ? `${highest[0]}.${highest[1]}.${highest[2] + 1}.0` : undefined;
}

function compareSegments(left: number[], right: number[]): number {
    for (let index = 0; index < Math.max(left.length, right.length); index++) {
        const difference = (left[index] ?? 0) - (right[index] ?? 0);
        if (difference !== 0) {
            return difference;
        }
    }

    return 0;
}

export function evaluateMilestoneLabel({ baseRefName, defaultBranch, labels, versionBranches, isDraft = false }: MilestoneLabelInput): MilestoneVerdict {
    if (labels.includes(SKIP_CHECK_LABEL)) {
        return { status: 'skipped', message: `\`${SKIP_CHECK_LABEL}\` is set` };
    }

    const milestoneLabels = labels.filter((label) => label.startsWith(MILESTONE_LABEL_PREFIX));
    if (milestoneLabels.length > 1) {
        return { status: 'invalid', message: `carries ${milestoneLabels.length} milestone labels (${milestoneLabels.join(', ')}), expected exactly one` };
    }

    const baseLine = releaseLineOf(baseRefName);

    // A stacked PR lands wherever its parent merges, so there is nothing to judge yet.
    if (baseRefName !== defaultBranch && baseLine === undefined) {
        return { status: 'skipped', message: `base branch \`${baseRefName}\` is neither \`${defaultBranch}\` nor a version branch` };
    }

    const expected = expectedMilestone(versionBranches);
    const label = milestoneLabels[0];

    if (label === undefined) {
        if (isDraft) {
            return { status: 'skipped', message: `is a draft without a \`${MILESTONE_LABEL_PREFIX}*\` label; it gets one when it is marked ready for review` };
        }

        return {
            status: 'invalid',
            message: `has no \`${MILESTONE_LABEL_PREFIX}*\` label, so it would be released without being announced anywhere`,
            // On a version branch the right patch depends on release state this module cannot see.
            expected: baseLine === undefined ? expected : undefined,
        };
    }

    const version = milestoneVersionOf(label);
    if (version === undefined) {
        return { status: 'invalid', message: `\`${label}\` is not a \`${MILESTONE_LABEL_PREFIX}X.Y.Z.P\` version` };
    }

    if (baseLine !== undefined) {
        return version.startsWith(`${baseLine}.`)
            ? { status: 'ok', message: `\`${label}\` matches base branch \`${baseRefName}\`` }
            : { status: 'invalid', message: `\`${label}\` does not belong to base branch \`${baseRefName}\`` };
    }

    const line = version.split('.').slice(0, 3).join('.');
    if (!versionBranches.includes(`${line}.x`)) {
        return { status: 'ok', message: `\`${label}\` is still open — branch \`${line}.x\` does not exist yet` };
    }

    const backportLabel = `backport-${line}.x`;
    if (labels.includes(backportLabel)) {
        return { status: 'ok', message: `\`${label}\` is closed for new work, but \`${backportLabel}\` routes this PR into it on purpose` };
    }

    return {
        status: 'invalid',
        message: `\`${label}\` claims the ${line}.* release, but branch \`${line}.x\` already exists — anything merged into \`${defaultBranch}\` now ships later. `
            + `Set \`${MILESTONE_LABEL_PREFIX}${expected ?? '<next version>'}\`, or add \`${backportLabel}\` if this really is meant to be backported into ${line}.x.`,
        expected,
    };
}

type GraphqlClient = {
    graphql<T>(query: string, variables: Record<string, unknown>): Promise<T>;
};

type IssuesClient = {
    rest: {
        issues: {
            addLabels(options: { owner: string; repo: string; issue_number: number; labels: string[] }): Promise<unknown>;
            removeLabel(options: { owner: string; repo: string; issue_number: number; name: string }): Promise<unknown>;
        };
    };
};

type MergeGroupClient = {
    rest: {
        pulls: {
            get(options: { owner: string; repo: string; pull_number: number }): Promise<{
                data: { number: number; draft?: boolean; base: { ref: string }; labels: { name: string }[] };
            }>;
        };
        repos: {
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

const VERSION_BRANCHES_QUERY = /* GraphQL */ `
  query ($owner: String!, $repo: String!, $branchQuery: String!, $after: String) {
    repository(owner: $owner, name: $repo) {
      refs(refPrefix: "refs/heads/", query: $branchQuery, first: 100, after: $after) {
        pageInfo { hasNextPage endCursor }
        nodes { name }
      }
    }
  }`;

export async function fetchVersionBranches(github: GraphqlClient, { owner, repo }: Repo): Promise<string[]> {
    const branches: string[] = [];
    let after: string | undefined = undefined;

    do {
        const result: { repository: { refs: { pageInfo: { hasNextPage: boolean; endCursor: string | null }; nodes: { name: string }[] } } } =
            await github.graphql(VERSION_BRANCHES_QUERY, { owner, repo, branchQuery: VERSION_BRANCH_QUERY, after });

        const { pageInfo, nodes } = result.repository.refs;
        branches.push(...nodes.map((node) => node.name).filter((name) => VERSION_BRANCH_PATTERN.test(name)));
        after = pageInfo.hasNextPage ? pageInfo.endCursor ?? undefined : undefined;
    } while (after);

    return branches;
}

type PullRequestPayload = {
    number: number;
    base: { ref: string };
    labels?: { name: string }[];
    draft?: boolean;
};

type MergeGroupPayload = {
    head_ref: string;
    base_sha: string;
    head_sha: string;
};

type PullRequestContext = {
    eventName?: string;
    repo: Repo;
    payload: {
        pull_request?: PullRequestPayload;
        merge_group?: MergeGroupPayload;
        repository?: { default_branch?: string };
    };
};

type Toolkit = {
    github: GraphqlClient & IssuesClient & MergeGroupClient;
    core: Logger;
    context: PullRequestContext;
};

function pullRequestOf(context: PullRequestContext): PullRequestPayload {
    const pullRequest = context.payload.pull_request;
    if (!pullRequest) {
        throw new Error('This function can only be called on pull request events.');
    }

    return pullRequest;
}

function defaultBranchOf(context: PullRequestContext): string {
    return context.payload.repository?.default_branch ?? 'trunk';
}

/** `refs/heads/gh-readonly-queue/trunk/pr-18791-<base sha>` -> 18791 */
const MERGE_GROUP_REF_PATTERN = /\/pr-(\d+)-[0-9a-f]+$/;

type CheckedPullRequest = { number: number; baseRefName: string; labels: string[]; isDraft: boolean };

/**
 * The queue may batch several PRs into one group while the ref names only the last,
 * so commits are resolved back to their PRs. The ref-named PR is always included so
 * a failed lookup cannot silently pass it.
 */
async function pullRequestNumbersInMergeGroup(github: MergeGroupClient, core: Logger, repo: Repo, mergeGroup: MergeGroupPayload): Promise<number[]> {
    const numbers = new Set<number>();

    const named = MERGE_GROUP_REF_PATTERN.exec(mergeGroup.head_ref)?.[1];
    if (named) {
        numbers.add(Number(named));
    }

    try {
        const { data: comparison } = await github.rest.repos.compareCommitsWithBasehead({
            ...repo,
            basehead: `${mergeGroup.base_sha}...${mergeGroup.head_sha}`,
        });

        for (const commit of comparison.commits) {
            const { data: pulls } = await github.rest.repos.listPullRequestsAssociatedWithCommit({ ...repo, commit_sha: commit.sha });
            for (const pull of pulls) {
                numbers.add(pull.number);
            }
        }
    } catch (error) {
        core.warning(`Could not resolve the merge group's commits to pull requests, checking only #${named ?? 'none'}: ${error instanceof Error ? error.message : String(error)}`);
    }

    if (numbers.size === 0) {
        throw new Error(`Could not determine which pull requests merge group "${mergeGroup.head_ref}" contains.`);
    }

    return [...numbers].sort((left, right) => left - right);
}

async function pullRequestsToCheck({ github, core, context }: Toolkit): Promise<CheckedPullRequest[]> {
    const mergeGroup = context.payload.merge_group;

    if (context.eventName !== 'merge_group' || !mergeGroup) {
        const pullRequest = pullRequestOf(context);

        return [{
            number: pullRequest.number,
            baseRefName: pullRequest.base.ref,
            labels: (pullRequest.labels ?? []).map((label) => label.name),
            isDraft: pullRequest.draft ?? false,
        }];
    }

    const numbers = await pullRequestNumbersInMergeGroup(github, core, context.repo, mergeGroup);

    return Promise.all(numbers.map(async (number) => {
        const { data } = await github.rest.pulls.get({ ...context.repo, pull_number: number });

        return {
            number: data.number,
            baseRefName: data.base.ref,
            labels: data.labels.map((label) => label.name),
            isDraft: data.draft ?? false,
        };
    }));
}

/**
 * Fails the run when a milestone label contradicts its base branch.
 *
 * `merge_group` is the gate that matters: it is always evaluated against the final
 * base, while the `pull_request` run can be green from before a silent retarget.
 * Reports only, so a check never changes what it is checking.
 */
export async function checkMilestoneLabel(toolkit: Toolkit): Promise<void> {
    const { github, core, context } = toolkit;
    const defaultBranch = defaultBranchOf(context);
    const versionBranches = await fetchVersionBranches(github, context.repo);
    const pullRequests = await pullRequestsToCheck(toolkit);

    const summary: string[] = [];
    const failures: string[] = [];

    for (const pullRequest of pullRequests) {
        const verdict = evaluateMilestoneLabel({
            baseRefName: pullRequest.baseRefName,
            defaultBranch,
            labels: pullRequest.labels,
            versionBranches,
            isDraft: pullRequest.isDraft,
        });

        const icon = { ok: '✅', skipped: '➖', invalid: '❌' }[verdict.status];
        summary.push(`${icon} #${pullRequest.number} ${verdict.message}`);

        if (verdict.status === 'invalid') {
            failures.push(`PR #${pullRequest.number} ${verdict.message}`);
        } else {
            core.info(`PR #${pullRequest.number} ${verdict.message}`);
        }
    }

    await core.summary.addRaw(`## Milestone label\n\n${summary.map((line) => `- ${line}`).join('\n')}\n`).write();

    if (failures.length > 0) {
        core.setFailed(failures.join('\n'));
    }
}

/**
 * Sets the label a PR should carry after its base branch changed. Only acts when the
 * label is unambiguously fixable; the rest is left for {@link checkMilestoneLabel}.
 */
export async function reconcileMilestoneLabel({ github, core, context }: Toolkit): Promise<void> {
    const pullRequest = pullRequestOf(context);
    const labels = (pullRequest.labels ?? []).map((label) => label.name);
    const verdict = evaluateMilestoneLabel({
        baseRefName: pullRequest.base.ref,
        defaultBranch: defaultBranchOf(context),
        labels,
        versionBranches: await fetchVersionBranches(github, context.repo),
        isDraft: pullRequest.draft,
    });

    if (verdict.status !== 'invalid') {
        core.info(`PR #${pullRequest.number} ${verdict.message} — nothing to fix`);

        return;
    }

    if (!verdict.expected) {
        core.warning(`PR #${pullRequest.number} ${verdict.message} — no unambiguous fix, leaving it to the check`);

        return;
    }

    await applyMilestoneLabel(github, core, context.repo, pullRequest.number, labels, verdict.expected);
}

async function applyMilestoneLabel(github: IssuesClient, core: Logger, repo: Repo, number: number, labels: string[], expected: string): Promise<void> {
    const target = `${MILESTONE_LABEL_PREFIX}${expected}`;

    for (const stale of labels.filter((label) => label.startsWith(MILESTONE_LABEL_PREFIX) && label !== target)) {
        await github.rest.issues.removeLabel({ ...repo, issue_number: number, name: stale });
        core.info(`Removed \`${stale}\` from #${number}`);
    }

    await github.rest.issues.addLabels({ ...repo, issue_number: number, labels: [target] });
    core.info(`Set \`${target}\` on #${number}`);
}

const OPEN_PULL_REQUESTS_QUERY = /* GraphQL */ `
  query ($owner: String!, $repo: String!, $baseRefName: String!, $after: String) {
    repository(owner: $owner, name: $repo) {
      pullRequests(baseRefName: $baseRefName, states: OPEN, first: 100, after: $after) {
        pageInfo { hasNextPage endCursor }
        nodes { number isDraft labels(first: 50) { nodes { name } } }
      }
    }
  }`;

/** Re-evaluates every open PR against the default branch and repairs contradicting labels. */
export async function reconcileOpenMilestoneLabels({ github, core, context }: Toolkit, dryRun: boolean): Promise<void> {
    const defaultBranch = defaultBranchOf(context);
    const versionBranches = await fetchVersionBranches(github, context.repo);

    if (dryRun) {
        core.info('Running in DRY RUN mode - no labels will be changed.');
    }

    const pullRequests: { number: number; labels: string[]; isDraft: boolean }[] = [];
    let after: string | undefined = undefined;

    do {
        const result: {
            repository: {
                pullRequests: {
                    pageInfo: { hasNextPage: boolean; endCursor: string | null };
                    nodes: { number: number; isDraft: boolean; labels: { nodes: { name: string }[] } }[];
                };
            };
        } = await github.graphql(OPEN_PULL_REQUESTS_QUERY, { ...context.repo, baseRefName: defaultBranch, after });

        const { pageInfo, nodes } = result.repository.pullRequests;
        pullRequests.push(...nodes.map((node) => ({ number: node.number, isDraft: node.isDraft, labels: node.labels.nodes.map((label) => label.name) })));
        after = pageInfo.hasNextPage ? pageInfo.endCursor ?? undefined : undefined;
    } while (after);

    const failed: number[] = [];
    let fixed = 0;

    for (const pullRequest of pullRequests) {
        const verdict = evaluateMilestoneLabel({ baseRefName: defaultBranch, defaultBranch, labels: pullRequest.labels, versionBranches, isDraft: pullRequest.isDraft });
        if (verdict.status !== 'invalid') {
            continue;
        }

        if (!verdict.expected) {
            core.warning(`PR #${pullRequest.number} ${verdict.message} — no unambiguous fix, skipping`);
            continue;
        }

        if (dryRun) {
            core.info(`Would set \`${MILESTONE_LABEL_PREFIX}${verdict.expected}\` on #${pullRequest.number}: ${verdict.message}`);
            ++fixed;
            continue;
        }

        // One PR failing must not hide the rest.
        try {
            await applyMilestoneLabel(github, core, context.repo, pullRequest.number, pullRequest.labels, verdict.expected);
            ++fixed;
        } catch (error) {
            failed.push(pullRequest.number);
            core.error(`Failed to fix the milestone label on #${pullRequest.number}: ${error instanceof Error ? error.message : String(error)}`);
        }
    }

    core.info(`Checked ${pullRequests.length} open PR(s) against \`${defaultBranch}\`, ${dryRun ? 'would have fixed' : 'fixed'} ${fixed}.`);

    if (failed.length > 0) {
        throw new Error(`Failed to fix the milestone label on ${failed.length} PR(s): ${failed.map((number) => `#${number}`).join(', ')}`);
    }
}
