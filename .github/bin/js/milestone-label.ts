/**
 * Validate and repair the `milestone/*` label of a pull request.
 *
 * The milestone label decides which release a change is announced under, so a stale
 * label is what sends RELEASE_INFO entries into an already-branched section. Two
 * things make it go stale:
 *
 * - A release branch is split off while the PR is open. The branch-off automation
 *   moves the label on open PRs, but a PR that is not open at that moment — or that
 *   the relabel run misses — keeps the old one.
 * - GitHub retargets a stacked PR to trunk once its parent merges. That produces an
 *   `AutomaticBaseChangeSucceededEvent` but no `pull_request` webhook, so no event
 *   re-evaluates the label. This is why the check alone is not enough and a
 *   scheduled reconcile exists.
 *
 * The invariant: once branch `X.Y.Z.x` exists, the `X.Y.Z.*` line is closed and a PR
 * targeting the default branch must not claim it — unless it carries the matching
 * `backport-X.Y.Z.x` label, which routes it into that branch on purpose.
 */

export const MILESTONE_LABEL_PREFIX = 'milestone/';

/** Opt-out for the deliberate exceptions the release owner makes. */
export const SKIP_CHECK_LABEL = 'skip-milestone-check';

/**
 * Narrows the ref search when listing version branches. The repository has ~700
 * branches, so an unfiltered listing would cost eight pages per PR event; this
 * brings it down to one. Widen it when a `7.` series starts.
 */
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
    /**
     * Drafts are held to the weaker rule: a wrong milestone is still wrong, but a
     * missing one is not, because authors deliberately drop the label from
     * long-running drafts and a draft cannot merge. The label comes back on
     * `ready_for_review`.
     */
    isDraft?: boolean;
};

/** The release line a version branch belongs to, or undefined when it isn't one. */
function releaseLineOf(branch: string): string | undefined {
    return VERSION_BRANCH_PATTERN.exec(branch)?.[1];
}

function milestoneVersionOf(label: string): string | undefined {
    const version = label.slice(MILESTONE_LABEL_PREFIX.length);

    return MILESTONE_VERSION_PATTERN.test(version) ? version : undefined;
}

/**
 * The milestone a PR against the default branch belongs to: the patch after the
 * highest already-branched one. Derived from the branches rather than from the
 * latest tag, so it stays correct in the window between a branch-off and the
 * matching release — which is exactly the window where labels go wrong.
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
        // Never guess which of them was meant.
        return { status: 'invalid', message: `carries ${milestoneLabels.length} milestone labels (${milestoneLabels.join(', ')}), expected exactly one` };
    }

    const baseLine = releaseLineOf(baseRefName);

    // A stacked PR targets another PR's head branch. Which release it lands in
    // depends on where the parent merges, so there is nothing to validate yet —
    // it gets validated once GitHub retargets it.
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
            // On a version branch the milestone depends on whether the minor is already
            // released, which this module does not know — don't offer a wrong fix.
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

/** What the rule needs about a pull request, from either event's payload. */
type CheckedPullRequest = { number: number; baseRefName: string; labels: string[]; isDraft: boolean };

/**
 * The pull requests a merge group is about to merge.
 *
 * The queue may batch several PRs into one group and the ref names only the last of
 * them, so every commit in the group is resolved back to its pull request. The
 * ref-named PR is always included, so an incomplete commit lookup still leaves the
 * queued PR checked rather than silently passing it.
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
 * Fails the run when a pull request's milestone label contradicts its base branch.
 *
 * Handles both `pull_request` (fast feedback while the PR is open) and `merge_group`
 * (the gate that actually prevents the merge). The merge group run is the one that
 * matters: GitHub retargets a stacked PR to the default branch without emitting any
 * `pull_request` event, so the PR-level run can be green from before the retarget,
 * while the merge group is always evaluated against the final base.
 *
 * Reports only — the fix is left to a human or to {@link reconcileMilestoneLabel}, so
 * a check run never silently changes what it is checking.
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
 * Sets the milestone label a PR should carry. Used after a base branch change,
 * where the previous label was chosen for a different base.
 *
 * Only acts when the label is both wrong and unambiguously fixable; anything else
 * is left for {@link checkMilestoneLabel} to report.
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

/**
 * Re-evaluates every open PR against the default branch and repairs the labels that
 * contradict it.
 *
 * This is the backstop for the case no webhook covers: GitHub retargeting a stacked
 * PR to the default branch emits no `pull_request` event, so without a periodic
 * sweep such a PR can be merged with a label from before the retarget.
 */
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
