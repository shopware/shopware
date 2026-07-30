import assert from 'node:assert/strict';
import { test } from 'node:test';
import { checkMilestoneLabel, evaluateMilestoneLabel, expectedMilestone, MILESTONE_LABEL_PREFIX, SKIP_CHECK_LABEL } from './milestone-label.ts';

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
    const failed: string[] = [];
    const warnings: string[] = [];

    return {
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

                            return { data: { number: pull_number, draft: pull.draft ?? false, base: { ref: pull.base }, labels: pull.labels.map((name) => ({ name })) } };
                        },
                    },
                    repos: {
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

test('the merge group gate fails a queued PR whose label is already branched', () => {
    // This is the case no pull_request event covers: GitHub retargeted the PR to
    // trunk silently, so the PR-level run never re-evaluated it.
    const { toolkit, failed } = mergeGroupToolkit(
        { 18791: { base: 'trunk', labels: [`${MILESTONE_LABEL_PREFIX}6.7.13.0`] } },
        [{ sha: 'c1', pulls: [18791] }],
    );

    return checkMilestoneLabel(toolkit as never).then(() => {
        assert.equal(failed.length, 1);
        assert.match(failed[0], /#18791/);
        assert.match(failed[0], /6\.7\.13\.x` already exists/);
    });
});

test('the merge group gate passes a correctly labelled queued PR', () => {
    const { toolkit, failed } = mergeGroupToolkit(
        { 18791: { base: 'trunk', labels: [`${MILESTONE_LABEL_PREFIX}6.7.14.0`] } },
        [{ sha: 'c1', pulls: [18791] }],
    );

    return checkMilestoneLabel(toolkit as never).then(() => assert.deepEqual(failed, []));
});

test('the merge group gate checks every PR in a batch, not just the one the ref names', () => {
    const { toolkit, failed } = mergeGroupToolkit(
        {
            18791: { base: 'trunk', labels: [`${MILESTONE_LABEL_PREFIX}6.7.14.0`] },
            18780: { base: 'trunk', labels: [`${MILESTONE_LABEL_PREFIX}6.7.13.0`] },
        },
        [{ sha: 'c1', pulls: [18780] }, { sha: 'c2', pulls: [18791] }],
    );

    return checkMilestoneLabel(toolkit as never).then(() => {
        assert.equal(failed.length, 1);
        assert.match(failed[0], /#18780/);
    });
});

test('the merge group gate still checks the ref-named PR when the commit lookup fails', () => {
    const { toolkit, failed, warnings } = mergeGroupToolkit(
        { 18791: { base: 'trunk', labels: [`${MILESTONE_LABEL_PREFIX}6.7.13.0`] } },
        [],
    );
    toolkit.github.rest.repos.compareCommitsWithBasehead = async () => {
        throw new Error('boom');
    };

    return checkMilestoneLabel(toolkit as never).then(() => {
        assert.equal(warnings.length, 1);
        assert.equal(failed.length, 1);
        assert.match(failed[0], /#18791/);
    });
});
