/**
 * Merge-queue gate for the label-triggered major lanes.
 *
 * `integration-major` and `acceptance-major` only run on pull requests that carry a
 * major-test label, and the merge queue does not run them at all, so a red major lane
 * never blocked a merge (#20934 broke them on trunk this way). The gate closes that gap
 * without running the lanes a second time: for every pull request in the merge group,
 * each lane its labels select must have passed on the pull request's head commit.
 * Pull requests without a major-test label pass straight through.
 *
 * Lanes still running when the group is created are waited for, so a pull request
 * enqueued right after a push is not dropped from the queue for being early.
 */

import { pullRequestNumbersInMergeGroup } from './milestone-label.ts';

/** Aggregating check run of each lane, as it shows up on the pull request. */
export const LANES = [
    { check: 'integration-major-check', labels: ['major-php', 'major-js', 'major-tests'] },
    { check: 'acceptance-major / acceptance-major-check', labels: ['major-acceptance', 'major-tests'] },
    { check: 'downstream-major-check', labels: ['major-tests'] },
] as const;

export type CheckRun = {
    name: string;
    status: string;
    conclusion: string | null;
    started_at: string | null;
    html_url?: string | null;
};

export type LaneVerdict = {
    check: string;
    state: 'success' | 'failure' | 'pending';
    message: string;
};

export function requiredLanes(labels: string[]): string[] {
    return LANES.filter((lane) => lane.labels.some((label) => labels.includes(label))).map((lane) => lane.check);
}

/**
 * Judges a lane by its most recent run that was not skipped. Skipped runs come from
 * events that do not concern the lane — an unrelated `labeled` event, or the run that
 * fired before the label was added — so they neither pass nor fail it.
 */
export function evaluateLane(check: string, runs: CheckRun[]): LaneVerdict {
    const latest = runs
        .filter((run) => run.name === check && run.conclusion !== 'skipped')
        .sort((left, right) => (right.started_at ?? '').localeCompare(left.started_at ?? ''))[0];

    if (!latest) {
        return { check, state: 'failure', message: `${check} has not run; push a commit or re-add the label to start it` };
    }

    if (latest.status !== 'completed') {
        return { check, state: 'pending', message: `${check} is still ${latest.status}` };
    }

    return latest.conclusion === 'success'
        ? { check, state: 'success', message: `${check} passed` }
        : { check, state: 'failure', message: `${check} concluded ${latest.conclusion}${latest.html_url ? `: ${latest.html_url}` : ''}` };
}

type Repo = { owner: string; repo: string };

type Logger = {
    info(message: string): void;
    warning(message: string): void;
    error(message: string): void;
    setFailed(message: string): void;
    summary: {
        addRaw(text: string): { write(): Promise<unknown> };
    };
};

type GateClient = {
    rest: {
        pulls: {
            get(options: { owner: string; repo: string; pull_number: number }): Promise<{
                data: { number: number; head: { sha: string }; labels: { name: string }[] };
            }>;
        };
        checks: {
            listForRef(options: { owner: string; repo: string; ref: string; check_name: string; filter: 'all'; per_page: number }): Promise<{
                data: { check_runs: CheckRun[] };
            }>;
        };
        repos: {
            compareCommitsWithBasehead(options: { owner: string; repo: string; basehead: string }): Promise<{ data: { commits: { sha: string }[] } }>;
            listPullRequestsAssociatedWithCommit(options: { owner: string; repo: string; commit_sha: string }): Promise<{ data: { number: number }[] }>;
        };
    };
};

type GateContext = {
    repo: Repo;
    payload: {
        merge_group?: { head_ref: string; base_sha: string; head_sha: string };
    };
};

type GateOptions = {
    pollIntervalMs?: number;
    timeoutMs?: number;
    sleep?: (ms: number) => Promise<void>;
    now?: () => number;
};

type PullRequestVerdict = { number: number; verdicts: LaneVerdict[] };

async function judgePullRequest(github: GateClient, repo: Repo, number: number): Promise<PullRequestVerdict> {
    const { data } = await github.rest.pulls.get({ ...repo, pull_number: number });
    const lanes = requiredLanes(data.labels.map((label) => label.name));

    const verdicts = await Promise.all(lanes.map(async (check) => {
        const { data: runs } = await github.rest.checks.listForRef({ ...repo, ref: data.head.sha, check_name: check, filter: 'all', per_page: 100 });

        return evaluateLane(check, runs.check_runs);
    }));

    return { number, verdicts };
}

export async function checkMajorLanes(
    { github, core, context }: { github: GateClient; core: Logger; context: GateContext },
    { pollIntervalMs = 60_000, timeoutMs = 50 * 60_000, sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms)), now = Date.now }: GateOptions = {},
): Promise<void> {
    const mergeGroup = context.payload.merge_group;
    if (!mergeGroup) {
        throw new Error('The major lanes gate only runs on merge_group events.');
    }

    const numbers = await pullRequestNumbersInMergeGroup(github, core, context.repo, mergeGroup);
    const deadline = now() + timeoutMs;
    let judged: PullRequestVerdict[];

    for (;;) {
        judged = await Promise.all(numbers.map((number) => judgePullRequest(github, context.repo, number)));
        const pending = judged.flatMap(({ number, verdicts }) => verdicts.filter((verdict) => verdict.state === 'pending').map((verdict) => `#${number} ${verdict.message}`));

        if (pending.length === 0 || now() >= deadline) {
            break;
        }

        core.info(`Waiting for: ${pending.join('; ')}`);
        await sleep(pollIntervalMs);
    }

    const lines = judged.map(({ number, verdicts }) => verdicts.length === 0
        ? `- ➖ #${number} carries no major-test label`
        : verdicts.map((verdict) => `- ${{ success: '✅', failure: '❌', pending: '⏳' }[verdict.state]} #${number} ${verdict.message}`).join('\n'));
    await core.summary.addRaw(`## Major lanes\n\n${lines.join('\n')}\n`).write();

    const blocking = judged.flatMap(({ number, verdicts }) => verdicts.filter((verdict) => verdict.state !== 'success').map((verdict) => `#${number} ${verdict.message}`));
    if (blocking.length > 0) {
        core.setFailed(`Major lanes not green: ${blocking.join('; ')}`);

        return;
    }

    core.info(`Major lanes green for ${numbers.map((number) => `#${number}`).join(', ')}`);
}
