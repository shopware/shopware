import assert from 'node:assert/strict';
import { test } from 'node:test';
import { checkMilestoneLabel, evaluateMilestoneLabel, expectedMilestone, MILESTONE_LABEL_PREFIX, SKIP_CHECK_LABEL, STATUS_CONTEXT } from './milestone-label.ts';

// The state of shopware/shopware after the 6.7.13.x branch-off: 6.7.13.* is closed,
// 6.7.14.0 is what trunk collects.
const VERSION_BRANCHES = ['6.5.x', '6.6.x', '6.7.11.x', '6.7.12.x', '6.7.13.x'];

const evaluate = (labels: string[], baseRefName = 'trunk', versionBranches = VERSION_BRANCHES) =>
    evaluateMilestoneLabel({ baseRefName, defaultBranch: 'trunk', labels, versionBranches });

const evaluateDraft = (labels: string[], baseRefName = 'trunk') =>
    evaluateMilestoneLabel({ baseRefName, defaultBranch: 'trunk', labels, versionBranches: VERSION_BRANCHES, isDraft: true });

test('expectedMilestone is the patch after the highest branched minor', () => {
    assert.equal(expectedMilestone(VERSION_BRANCHES), '6.7.14.0');
});

test('expectedMilestone ignores whole-major branches like 6.6.x', () => {
    assert.equal(expectedMilestone(['6.6.x', '6.5.x']), undefined);
});

test('expectedMilestone compares segments numerically, not alphabetically', () => {
    // "6.7.9.x" sorts after "6.7.13.x" as a string
    assert.equal(expectedMilestone(['6.7.9.x', '6.7.13.x']), '6.7.14.0');
});

test('a trunk PR labelled with the upcoming version is fine', () => {
    const verdict = evaluate([`${MILESTONE_LABEL_PREFIX}6.7.14.0`]);

    assert.equal(verdict.status, 'ok');
});

test('a trunk PR labelled with an already-branched version is invalid and gets a fix', () => {
    // Regression: this is #17997, which merged into trunk after the 6.7.13.x branch-off
    // and put its RELEASE_INFO entries under 6.7.13.0.
    const verdict = evaluate([`${MILESTONE_LABEL_PREFIX}6.7.13.0`]);

    assert.equal(verdict.status, 'invalid');
    assert.equal(verdict.status === 'invalid' && verdict.expected, '6.7.14.0');
    assert.match(verdict.message, /6\.7\.13\.x` already exists/);
});

test('an already-branched version is allowed when the matching backport label routes it there', () => {
    // Regression: this is #18517, where the milestone was set to 6.7.13.0 on purpose.
    const verdict = evaluate([`${MILESTONE_LABEL_PREFIX}6.7.13.0`, 'backport-6.7.13.x']);

    assert.equal(verdict.status, 'ok');
});

test('a backport label for a different line does not excuse the label', () => {
    const verdict = evaluate([`${MILESTONE_LABEL_PREFIX}6.7.13.0`, 'backport-6.7.12.x']);

    assert.equal(verdict.status, 'invalid');
});

test('a trunk PR without any milestone label is invalid', () => {
    // Regression: PRs opened against a feature branch never get a label at all,
    // and keep having none after GitHub retargets them to trunk.
    const verdict = evaluate(['component/core']);

    assert.equal(verdict.status, 'invalid');
    assert.equal(verdict.status === 'invalid' && verdict.expected, '6.7.14.0');
    assert.match(verdict.message, /no `milestone\/\*` label/);
});

test('a missing label is a race while the labeler is still running', () => {
    // Regression: this failed on every newly opened pull request.
    const racing = evaluateMilestoneLabel({
        baseRefName: 'trunk',
        defaultBranch: 'trunk',
        labels: [],
        versionBranches: VERSION_BRANCHES,
        labelerPending: true,
    });

    assert.equal(racing.status, 'skipped');
    assert.equal(evaluate([]).status, 'invalid');
});

test('a wrong label is invalid even while the labeler is still running', () => {
    const verdict = evaluateMilestoneLabel({
        baseRefName: 'trunk',
        defaultBranch: 'trunk',
        labels: [`${MILESTONE_LABEL_PREFIX}6.7.13.0`],
        versionBranches: VERSION_BRANCHES,
        labelerPending: true,
    });

    assert.equal(verdict.status, 'invalid');
});

test('a draft without a milestone label is left alone', () => {
    // Authors deliberately drop the label from long-running drafts, and a draft
    // cannot merge — set_milestone_label puts it back on ready_for_review.
    assert.equal(evaluateDraft(['component/core']).status, 'skipped');
});

test('a draft with a wrong milestone label is still invalid', () => {
    const verdict = evaluateDraft([`${MILESTONE_LABEL_PREFIX}6.7.13.0`]);

    assert.equal(verdict.status, 'invalid');
    assert.equal(verdict.status === 'invalid' && verdict.expected, '6.7.14.0');
});

test('a stacked PR is not judged while it targets another PR head branch', () => {
    const verdict = evaluate([`${MILESTONE_LABEL_PREFIX}6.7.13.0`], 'feat/mcp-list-changed-notifications');

    assert.equal(verdict.status, 'skipped');
});

test('a PR against a version branch must carry a label of that line', () => {
    assert.equal(evaluate([`${MILESTONE_LABEL_PREFIX}6.7.13.0`], '6.7.13.x').status, 'ok');
    assert.equal(evaluate([`${MILESTONE_LABEL_PREFIX}6.7.13.2`], '6.7.13.x').status, 'ok');
    assert.equal(evaluate([`${MILESTONE_LABEL_PREFIX}6.7.14.0`], '6.7.13.x').status, 'invalid');
});

test('a PR against a whole-major branch may carry any patch of that major', () => {
    assert.equal(evaluate([`${MILESTONE_LABEL_PREFIX}6.6.10.5`], '6.6.x').status, 'ok');
    assert.equal(evaluate([`${MILESTONE_LABEL_PREFIX}6.7.14.0`], '6.6.x').status, 'invalid');
});

test('a version-branch PR gets no suggested fix, because the right patch is unknown here', () => {
    const verdict = evaluate([], '6.7.13.x');

    assert.equal(verdict.status, 'invalid');
    assert.equal(verdict.status === 'invalid' && verdict.expected, undefined);
});

test('more than one milestone label is invalid and never guessed', () => {
    const verdict = evaluate([`${MILESTONE_LABEL_PREFIX}6.7.13.0`, `${MILESTONE_LABEL_PREFIX}6.7.14.0`]);

    assert.equal(verdict.status, 'invalid');
    assert.equal(verdict.status === 'invalid' && verdict.expected, undefined);
});

test('a malformed milestone label is invalid and never guessed', () => {
    const verdict = evaluate([`${MILESTONE_LABEL_PREFIX}6.7.x`]);

    assert.equal(verdict.status, 'invalid');
    assert.equal(verdict.status === 'invalid' && verdict.expected, undefined);
});

test(`${SKIP_CHECK_LABEL} opts out of everything`, () => {
    const verdict = evaluate([`${MILESTONE_LABEL_PREFIX}6.7.13.0`, SKIP_CHECK_LABEL]);

    assert.equal(verdict.status, 'skipped');
});

/** Toolkit stub for the merge-group path; `pulls` maps a PR number to its state. */
function mergeGroupToolkit(
    pulls: Record<number, { base: string; labels: string[]; draft?: boolean }>,
    commits: { sha: string; pulls: number[] }[],
    headRef = 'refs/heads/gh-readonly-queue/trunk/pr-18791-0123456789abcdef0123456789abcdef01234567',
) {
    const statuses: { state: string; description: string }[] = [];
    const failed: string[] = [];
    const warnings: string[] = [];

    return {
        statuses,
        failed,
        warnings,
        toolkit: {
            github: {
                graphql: async () => ({
                    repository: { refs: { pageInfo: { hasNextPage: false, endCursor: null }, nodes: VERSION_BRANCHES.map((name) => ({ name })) } },
                }),
                rest: {
                    issues: { addLabels: async () => ({}), removeLabel: async () => ({}) },
                    pulls: {
                        get: async ({ pull_number }: { pull_number: number }) => {
                            const pull = pulls[pull_number];
                            if (!pull) {
                                throw new Error(`unexpected pulls.get for #${pull_number}`);
                            }

                            return { data: { number: pull_number, draft: pull.draft ?? false, base: { ref: pull.base }, head: { sha: `head-${pull_number}` }, labels: pull.labels.map((name) => ({ name })) } };
                        },
                    },
                    repos: {
                        createCommitStatus: async ({ state, description, context: ctx }: { state: string; description: string; context: string }) => {
                            assert.equal(ctx, STATUS_CONTEXT);
                            assert.ok(description.length <= 140, `status description too long: ${description.length}`);
                            statuses.push({ state, description });

                            return {};
                        },
                        compareCommitsWithBasehead: async () => ({ data: { commits: commits.map(({ sha }) => ({ sha })) } }),
                        listPullRequestsAssociatedWithCommit: async ({ commit_sha }: { commit_sha: string }) => ({
                            data: (commits.find(({ sha }) => sha === commit_sha)?.pulls ?? []).map((number) => ({ number })),
                        }),
                    },
                },
            },
            core: {
                info: () => {},
                warning: (message: string) => warnings.push(message),
                error: () => {},
                setFailed: (message: string) => failed.push(message),
                summary: { addRaw: () => ({ write: async () => {} }) },
            },
            context: {
                eventName: 'merge_group',
                repo: { owner: 'shopware', repo: 'shopware' },
                payload: {
                    repository: { default_branch: 'trunk' },
                    merge_group: { head_ref: headRef, base_sha: 'aaaa', head_sha: 'bbbb' },
                },
            },
        },
    };
}

/**
 * Toolkit stub for the single pull-request path (pull_request_target / merge_group's sibling).
 * `pull` is the state the API reports, `stale` what the event payload still claims — they
 * diverge whenever a label lands after the event fired, or the run is a re-run.
 */
function pullRequestToolkit(
    eventName: string,
    pull: { base: string; labels: string[]; draft?: boolean; sha?: string },
    stale: { base?: string; labels?: string[]; draft?: boolean; sha?: string } = {},
) {
    const statuses: { state: string; description: string; sha: string }[] = [];

    return {
        statuses,
        toolkit: {
            github: {
                graphql: async () => ({
                    repository: { refs: { pageInfo: { hasNextPage: false, endCursor: null }, nodes: VERSION_BRANCHES.map((name) => ({ name })) } },
                }),
                rest: {
                    pulls: {
                        get: async ({ pull_number }: { pull_number: number }) => ({
                            data: {
                                number: pull_number,
                                draft: pull.draft ?? false,
                                base: { ref: pull.base },
                                head: { sha: pull.sha ?? 'cccc' },
                                labels: pull.labels.map((name) => ({ name })),
                            },
                        }),
                    },
                    repos: {
                        createCommitStatus: async ({ state, description, sha, context: ctx }: { state: string; description: string; sha: string; context: string }) => {
                            assert.equal(ctx, STATUS_CONTEXT);
                            statuses.push({ state, description, sha });

                            return {};
                        },
                    },
                },
            },
            core: {
                info: () => {},
                warning: () => {},
                error: () => {},
                setFailed: () => { throw new Error('the job must stay green; the verdict belongs in the commit status'); },
                summary: { addRaw: () => ({ write: async () => {} }) },
            },
            context: {
                eventName,
                repo: { owner: 'shopware', repo: 'shopware' },
                payload: {
                    repository: { default_branch: 'trunk' },
                    pull_request: {
                        number: 1,
                        base: { ref: stale.base ?? pull.base },
                        head: { sha: stale.sha ?? pull.sha ?? 'cccc' },
                        draft: stale.draft ?? pull.draft ?? false,
                        labels: (stale.labels ?? pull.labels).map((name) => ({ name })),
                    },
                },
            },
        },
    };
}

// Locks in "anything but merge_group" rather than a literal event-name check.
for (const eventName of ['pull_request_target', 'pull_request']) {
    test(`the single-PR path posts the commit status on a "${eventName}" event`, async () => {
        const { toolkit, statuses } = pullRequestToolkit(eventName, { base: 'trunk', labels: [`${MILESTONE_LABEL_PREFIX}6.7.13.0`] });

        await checkMilestoneLabel(toolkit as never);

        assert.equal(statuses.length, 1);
        assert.equal(statuses[0].state, 'failure');
    });
}

test('the single-PR path judges the label the API reports, not the one the payload froze', async () => {
    // The labeler wins the race by a second, so the payload of the event that started this
    // run has no label yet while the pull request already carries the right one (#20073).
    const { toolkit, statuses } = pullRequestToolkit(
        'pull_request_target',
        { base: 'trunk', labels: [`${MILESTONE_LABEL_PREFIX}6.7.14.0`] },
        { labels: [] },
    );

    await checkMilestoneLabel(toolkit as never);

    assert.equal(statuses[0].state, 'success');
});

test('the single-PR path posts the status on the head the API reports', async () => {
    // A re-run replays the original payload, so its head can be several pushes behind.
    const { toolkit, statuses } = pullRequestToolkit(
        'pull_request_target',
        { base: 'trunk', labels: [`${MILESTONE_LABEL_PREFIX}6.7.14.0`], sha: 'dddd' },
        { sha: 'cccc' },
    );

    await checkMilestoneLabel(toolkit as never);

    assert.equal(statuses[0].sha, 'dddd');
});

test('the single-PR path never fails the job, even when the label is wrong', async () => {
    const { toolkit } = pullRequestToolkit('pull_request_target', { base: 'trunk', labels: [] });

    // pullRequestToolkit's setFailed throws — reaching it would fail this test.
    await checkMilestoneLabel(toolkit as never);
});

test('the merge group gate fails a queued PR whose label is already branched', () => {
    // This is the case no pull_request event covers: GitHub retargeted the PR to
    // trunk silently, so the PR-level run never re-evaluated it.
    const { toolkit, failed, statuses } = mergeGroupToolkit(
        { 18791: { base: 'trunk', labels: [`${MILESTONE_LABEL_PREFIX}6.7.13.0`] } },
        [{ sha: 'c1', pulls: [18791] }],
    );

    return checkMilestoneLabel(toolkit as never).then(() => {
        // The job stays green; the verdict lives in the commit status.
        assert.deepEqual(failed, []);
        assert.equal(statuses.length, 1);
        assert.equal(statuses[0].state, 'failure');
        assert.match(statuses[0].description, /is closed for new work/);
    });
});

test('the merge group gate passes a correctly labelled queued PR', () => {
    const { toolkit, failed, statuses } = mergeGroupToolkit(
        { 18791: { base: 'trunk', labels: [`${MILESTONE_LABEL_PREFIX}6.7.14.0`] } },
        [{ sha: 'c1', pulls: [18791] }],
    );

    return checkMilestoneLabel(toolkit as never).then(() => {
        assert.deepEqual(failed, []);
        assert.equal(statuses[0].state, 'success');
    });
});

test('the merge group gate checks every PR in a batch, not just the one the ref names', () => {
    const { toolkit, failed, statuses } = mergeGroupToolkit(
        {
            18791: { base: 'trunk', labels: [`${MILESTONE_LABEL_PREFIX}6.7.14.0`] },
            18780: { base: 'trunk', labels: [`${MILESTONE_LABEL_PREFIX}6.7.13.0`] },
        },
        [{ sha: 'c1', pulls: [18780] }, { sha: 'c2', pulls: [18791] }],
    );

    return checkMilestoneLabel(toolkit as never).then(() => {
        assert.equal(statuses[0].state, 'failure');
        assert.match(statuses[0].description, /#18780/);
        assert.doesNotMatch(statuses[0].description, /#18791/);
    });
});

test('the merge group gate still checks the ref-named PR when the commit lookup fails', () => {
    const { toolkit, failed, warnings, statuses } = mergeGroupToolkit(
        { 18791: { base: 'trunk', labels: [`${MILESTONE_LABEL_PREFIX}6.7.13.0`] } },
        [],
    );
    toolkit.github.rest.repos.compareCommitsWithBasehead = async () => {
        throw new Error('boom');
    };

    return checkMilestoneLabel(toolkit as never).then(() => {
        assert.equal(warnings.length, 1);
        assert.equal(statuses[0].state, 'failure');
    });
});

test('every short form fits the commit status limit', () => {
    const cases = [
        [],
        ['component/core'],
        [SKIP_CHECK_LABEL],
        [`${MILESTONE_LABEL_PREFIX}6.7.14.0`],
        [`${MILESTONE_LABEL_PREFIX}6.7.13.0`],
        [`${MILESTONE_LABEL_PREFIX}6.7.13.0`, 'backport-6.7.13.x'],
        [`${MILESTONE_LABEL_PREFIX}6.7.13.0`, `${MILESTONE_LABEL_PREFIX}6.7.14.0`],
        [`${MILESTONE_LABEL_PREFIX}6.7.x`],
    ];

    for (const base of ['trunk', '6.7.13.x', 'feat/some-stacked-branch']) {
        for (const labels of cases) {
            const { short } = evaluateMilestoneLabel({ baseRefName: base, defaultBranch: 'trunk', labels, versionBranches: VERSION_BRANCHES });
            assert.ok(short.length <= 140, `too long (${short.length}) for base ${base}: ${short}`);
        }
    }
});
